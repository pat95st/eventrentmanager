<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage;

use App\Application\Mail\EmailTemplateRenderer;
use App\Domain\Customer\Customer;
use App\Domain\DocumentStorage\SharePasswordDelivery;
use App\Domain\DocumentStorage\SharePermission;
use App\Domain\DocumentStorage\StorageFolder;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\MailCategory;
use App\Domain\Messaging\MessagingChannel;
use App\Domain\Messaging\MessagingSettingsRepositoryInterface;
use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Messaging\MessageSenderException;
use App\Infrastructure\Messaging\MessageSenderInterface;

use function App\Infrastructure\Http\e;

/**
 * Freigabe-Link und Passwort an den Kunden: der Link über die bearbeitbare
 * E-Mail-Vorlage "Ordner-Freigabe", das Passwort über die Vorlage
 * "Ordner-Freigabe: Passwort" bzw. als kurze Textnachricht über Telegram
 * oder Signal. Link und Passwort stehen nie in derselben Nachricht.
 */
final class EmailStorageFolderShareNotifier implements StorageFolderShareNotifierInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EmailTemplateRenderer $templateRenderer,
        private readonly MessagingSettingsRepositoryInterface $messagingSettings,
        private readonly MessageSenderInterface $messageSender,
    ) {
    }

    public function notify(string $recipientEmail, string $customerName, StorageFolder $folder): void
    {
        $this->sendMail($recipientEmail, EmailTemplateKey::StorageFolderShare, [
            'customer_name' => e($customerName),
            'folder_name' => e($folder->name()),
            'link' => e((string) $folder->shareUrl),
            'permission' => e(($folder->sharePermission ?? SharePermission::Read)->label()),
            'password_line' => $folder->shareHasPassword
                ? '<p>Der Ordner ist mit einem Passwort geschützt. Das Passwort erhalten Sie separat von uns.</p>'
                : '',
        ]);
    }

    public function passwordDeliveryOptions(Customer $customer): array
    {
        $options = [];

        $email = trim((string) $customer->email);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $options[SharePasswordDelivery::Email->value] = 'E-Mail an ' . $email;
        }

        $settings = $this->messagingSettings->get();
        $chatId = trim((string) $customer->telegramChatId);
        if ($chatId !== '' && $settings->isChannelReady(MessagingChannel::Telegram)) {
            $options[SharePasswordDelivery::Telegram->value] = 'Telegram';
        }
        $phone = trim((string) $customer->phone);
        if ($phone !== '' && $settings->isChannelReady(MessagingChannel::Signal)) {
            $options[SharePasswordDelivery::Signal->value] = 'Signal an ' . $phone;
        }

        return $options;
    }

    public function sendPassword(SharePasswordDelivery $delivery, Customer $customer, StorageFolder $folder, string $password): void
    {
        if (!array_key_exists($delivery->value, $this->passwordDeliveryOptions($customer))) {
            throw new MessageSenderException("Der Versand per {$delivery->label()} ist für diesen Kunden nicht möglich.");
        }

        if ($delivery === SharePasswordDelivery::Email) {
            $this->sendMail(trim((string) $customer->email), EmailTemplateKey::StorageFolderSharePassword, [
                'customer_name' => e($customer->displayName()),
                'folder_name' => e($folder->name()),
                'password' => e($password),
            ]);

            return;
        }

        $text = "Guten Tag {$customer->displayName()},\n\ndas Passwort für den freigegebenen Ordner „{$folder->name()}“ lautet:\n\n{$password}";
        if ($delivery === SharePasswordDelivery::Telegram) {
            $this->messageSender->sendTelegramMessage(trim((string) $customer->telegramChatId), $text);
        } else {
            $this->messageSender->sendSignalMessage(trim((string) $customer->phone), $text);
        }
    }

    /** @param array<string, string> $variables */
    private function sendMail(string $recipient, EmailTemplateKey $template, array $variables): void
    {
        $rendered = $this->templateRenderer->render($template, $variables);

        $attachments = [];
        if ($rendered->logoAttachment !== null) {
            $attachments[] = $rendered->logoAttachment;
        }

        $this->mailer->send($recipient, $rendered->subject, $rendered->textBody, $attachments, $rendered->htmlBody, MailCategory::StorageFolderShare);
    }
}
