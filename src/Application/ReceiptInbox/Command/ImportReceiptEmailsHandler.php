<?php

declare(strict_types=1);

namespace App\Application\ReceiptInbox\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Mail\MailCategory;
use App\Domain\Push\PushSettingsRepositoryInterface;
use App\Domain\Push\PushSubscriptionRepositoryInterface;
use App\Domain\ReceiptInbox\ReceiptInboxAllowedSenderRepositoryInterface;
use App\Domain\ReceiptInbox\ReceiptInboxAttachment;
use App\Domain\ReceiptInbox\ReceiptInboxAttachmentRepositoryInterface;
use App\Domain\ReceiptInbox\ReceiptInboxMessage;
use App\Domain\ReceiptInbox\ReceiptInboxMessageRepositoryInterface;
use App\Domain\Settings\MailboxProtocol;
use App\Domain\Settings\MailboxSettingsRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Mail\ImapClient;
use App\Infrastructure\Mail\MailboxAttachment;
use App\Infrastructure\Mail\MailboxMessage;
use App\Infrastructure\Mail\MailerException;
use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Mail\Pop3Client;
use App\Infrastructure\Push\WebPushSender;
use App\Shared\Uuid;
use DateTimeImmutable;
use Throwable;

/**
 * Kern des Beleg-Posteingangs: ruft (sofern aktiviert) alle Nachrichten aus
 * dem konfigurierten Postfach ab (siehe MailboxClientInterface), filtert
 * sie über die Absender-Allow-Liste, dedupliziert über die Message-ID und
 * speichert neue Nachrichten samt ihrer PDF/JPG/PNG-Anhänge. Das Postfach
 * selbst wird dabei NIEMALS verändert — keine "gelesen"-Markierung, kein
 * Verschieben/Löschen, reines DB-seitiges Tracking (siehe
 * bin/import-receipt-emails.php, das diesen Command per Cronjob auslöst).
 */
final class ImportReceiptEmailsHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly MailboxSettingsRepositoryInterface $mailboxSettings,
        private readonly ReceiptInboxAllowedSenderRepositoryInterface $allowedSenders,
        private readonly ReceiptInboxMessageRepositoryInterface $messages,
        private readonly ReceiptInboxAttachmentRepositoryInterface $attachments,
        private readonly LocalFileStorage $fileStorage,
        private readonly UserRepositoryInterface $users,
        private readonly MailerInterface $mailer,
        // Zusätzliche, gleichwertige Zustellart NEBEN der E-Mail-
        // Benachrichtigung (siehe notifyAdministrators()/sendPushNotifications()
        // unten) — dasselbe User::$notifyOnNewReceiptEmail-Opt-in steuert
        // beide, es gibt bewusst kein separates Push-Häkchen.
        private readonly PushSettingsRepositoryInterface $pushSettings,
        private readonly PushSubscriptionRepositoryInterface $pushSubscriptions,
    ) {
    }

    public function handle(CommandInterface $command): ImportReceiptEmailsResult
    {
        assert($command instanceof ImportReceiptEmails);

        $settings = $this->mailboxSettings->get();

        if (!$settings->enabled) {
            return new ImportReceiptEmailsResult(disabled: true);
        }

        $client = $settings->protocol === MailboxProtocol::Imap
            ? new ImapClient($settings)
            : new Pop3Client($settings);

        $fetched = $client->fetchMessages();

        $allowedSenderEmails = array_map(
            static fn ($sender) => strtolower($sender->email),
            $this->allowedSenders->findAll(),
        );

        $importedMessages = 0;
        $importedAttachments = 0;
        $ignoredNotAllowed = 0;
        $skippedDuplicates = 0;
        $ignoredNoAttachment = 0;

        foreach ($fetched as $message) {
            if (!in_array(strtolower($message->fromAddress), $allowedSenderEmails, true)) {
                $ignoredNotAllowed++;

                continue;
            }

            if ($this->messages->findByMessageId($message->messageId) !== null) {
                $skippedDuplicates++;

                continue;
            }

            if ($message->attachments === []) {
                $ignoredNoAttachment++;

                continue;
            }

            $importedAttachments += $this->storeMessage($message);
            $importedMessages++;
        }

        if ($importedMessages > 0) {
            $this->notifyAdministrators($importedMessages);
            $this->sendPushNotifications($importedMessages);
        }

        return new ImportReceiptEmailsResult(
            disabled: false,
            importedMessages: $importedMessages,
            importedAttachments: $importedAttachments,
            ignoredNotAllowed: $ignoredNotAllowed,
            skippedDuplicates: $skippedDuplicates,
            ignoredNoAttachment: $ignoredNoAttachment,
        );
    }

    /** @return int Anzahl gespeicherter Anhänge für diese Nachricht. */
    private function storeMessage(MailboxMessage $message): int
    {
        $messageId = Uuid::v4();

        $inboxMessage = new ReceiptInboxMessage(
            id: $messageId,
            messageId: $message->messageId,
            fromAddress: $message->fromAddress,
            subject: $message->subject,
            receivedAt: $message->date,
            importedAt: new DateTimeImmutable(),
        );
        $this->messages->save($inboxMessage);

        $stored = 0;
        foreach ($message->attachments as $attachment) {
            $this->storeAttachment($messageId, $attachment);
            $stored++;
        }

        return $stored;
    }

    private function storeAttachment(string $messageId, MailboxAttachment $attachment): void
    {
        $attachmentId = Uuid::v4();

        $stored = $this->fileStorage->storeRawBytes(
            $attachment->rawBytes,
            'receipt-inbox',
            $attachmentId,
            $attachment->filename,
            $attachment->mimeType,
        );

        $inboxAttachment = new ReceiptInboxAttachment(
            id: $attachmentId,
            messageId: $messageId,
            originalFilename: $stored['originalFilename'],
            storedFilename: $stored['storedFilename'],
            mimeType: $stored['mimeType'],
            sizeBytes: $stored['sizeBytes'],
            convertedReceiptId: null,
            createdAt: new DateTimeImmutable(),
        );

        $this->attachments->save($inboxAttachment);
    }

    /** Benachrichtigt alle aktiven Administratoren mit gesetzter E-Mail-Adresse und aktivierter Benachrichtigung (siehe User::$notifyOnNewReceiptEmail). Ein Mailfehler bricht den Import selbst NICHT ab — die Nachrichten wurden bereits erfolgreich gespeichert. */
    private function notifyAdministrators(int $importedMessages): void
    {
        $recipients = array_filter(
            $this->users->findAll($this->users->countAll(), 0),
            static fn ($user) => $user->isActive
                && $user->isAdministrator()
                && $user->notifyOnNewReceiptEmail
                && trim($user->email) !== '',
        );

        if ($recipients === []) {
            return;
        }

        $subject = 'Neue Belege im E-Mail-Posteingang';
        $body = $importedMessages === 1
            ? "Es ist 1 neue Nachricht mit Beleg-Anhängen im Posteingang eingetroffen.\n\nZum Posteingang: /belege/posteingang"
            : "Es sind {$importedMessages} neue Nachrichten mit Beleg-Anhängen im Posteingang eingetroffen.\n\nZum Posteingang: /belege/posteingang";

        foreach ($recipients as $recipient) {
            try {
                $this->mailer->send($recipient->email, $subject, $body, [], null, MailCategory::ReceiptInboxNotification);
            } catch (MailerException) {
                // Bewusst ignoriert: ein einzelner Mailfehler soll weder den
                // Import noch die Benachrichtigung anderer Administratoren
                // verhindern (analog zu anderen "best effort"-Mailversänden
                // in dieser Anwendung).
            }
        }
    }

    /**
     * Sendet zusätzlich zur E-Mail-Benachrichtigung (siehe oben) eine echte
     * Browser-Push-Benachrichtigung an jedes registrierte Gerät/Browser
     * (App\Domain\Push\PushSubscription) jedes berechtigten Administrators
     * (dasselbe User::$notifyOnNewReceiptEmail-Opt-in wie bei der E-Mail,
     * kein separates Push-Häkchen). Vollständig fehlertolerant: WEDER ein
     * fehlendes/unvollständiges VAPID-Setup NOCH ein einzelner
     * fehlgeschlagener Versand (Netzwerkfehler, falsches Zertifikat, …)
     * dürfen den Cron-Import selbst zum Absturz bringen — alles landet
     * bestenfalls im Server-Error-Log (siehe try/catch unten), der
     * Cron-Exit-Code bleibt ausschließlich vom E-Mail-Import-Ergebnis
     * bestimmt (siehe bin/import-receipt-emails.php).
     */
    private function sendPushNotifications(int $importedMessages): void
    {
        try {
            $settings = $this->pushSettings->get();

            if (!$settings->isConfigured()) {
                // Noch kein VAPID-Schlüsselpaar erzeugt und/oder keine
                // Absender-E-Mail hinterlegt (siehe /einstellungen/push) —
                // ohne das ist kein Versand möglich, aber das ist ein
                // normaler, erwarteter Zustand (z. B. direkt nach der
                // Installation) und KEIN Fehler.
                return;
            }

            $recipients = array_filter(
                $this->users->findAll($this->users->countAll(), 0),
                static fn ($user) => $user->isActive
                    && $user->isAdministrator()
                    && $user->notifyOnNewReceiptEmail,
            );

            if ($recipients === []) {
                return;
            }

            $body = $importedMessages === 1
                ? 'Es ist 1 neuer Beleg eingetroffen.'
                : "Es sind {$importedMessages} neue Belege eingetroffen.";

            $payload = json_encode([
                'title' => 'Neuer Beleg im Posteingang',
                'body' => $body,
                'url' => '/belege/posteingang',
            ], JSON_THROW_ON_ERROR);

            $sender = new WebPushSender($settings->vapidPrivateKeyPem, $settings->vapidPublicKey, $settings->subjectEmail);

            foreach ($recipients as $recipient) {
                foreach ($this->pushSubscriptions->findAllForUser($recipient->id) as $subscription) {
                    try {
                        $result = $sender->send($subscription, $payload);

                        if ($result->isGone()) {
                            // HTTP 404/410: der Push-Dienst meldet, dass
                            // diese Subscription abgelaufen/ungültig ist
                            // (z. B. Browser deinstalliert) — Selbst-
                            // bereinigung statt bei jedem weiteren Import
                            // erneut erfolglos zu versuchen.
                            $this->pushSubscriptions->delete($subscription->id);
                        }
                    } catch (Throwable $e) {
                        error_log(sprintf(
                            '[Materialverleih] Push-Benachrichtigung fehlgeschlagen (Subscription %s, Benutzer %s): %s',
                            $subscription->id,
                            $recipient->id,
                            $e->getMessage(),
                        ));
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[Materialverleih] Push-Benachrichtigungen für den Beleg-Posteingang konnten nicht verarbeitet werden: ' . $e->getMessage());
        }
    }
}
