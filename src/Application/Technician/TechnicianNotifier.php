<?php

declare(strict_types=1);

namespace App\Application\Technician;

use App\Application\Mail\EmailTemplateRenderer;
use App\Application\Messaging\MessagingTemplateRenderer;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\MailCategory;
use App\Domain\Messaging\MessageTemplateKey;
use App\Domain\Messaging\MessagingChannel;
use App\Domain\Messaging\MessagingSettingsRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Domain\Settings\IntegrationBaseSettingsRepositoryInterface;
use App\Domain\Settings\SmtpSettingsRepositoryInterface;
use App\Domain\Technician\RentalTechnician;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\RentalTechnicianStatus;
use App\Domain\Technician\TechnicianProfileRepositoryInterface;
use App\Domain\Technician\TradeRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Mail\MailAttachment;
use App\Infrastructure\Mail\MailerInterface;
use App\Infrastructure\Messaging\MessageSenderInterface;
use DateTimeImmutable;

use function App\Infrastructure\Http\e;

/**
 * Benachrichtigt einen Techniker über eine Anfrage, Erinnerung oder
 * Einplanung (Migration 134): immer per E-Mail mit Termineinladung (.ics),
 * zusätzlich über jeden aktivierten Messenger, für den der Techniker
 * Kontaktdaten hat (Mobilnummer für WhatsApp/Signal, Telegram-Chat-ID).
 * Jeder Kanal wird einzeln versucht; Fehler werden gesammelt zurückgegeben.
 */
final class TechnicianNotifier
{
    public function __construct(
        private readonly RentalTechnicianRepositoryInterface $assignments,
        private readonly RentalRepositoryInterface $rentals,
        private readonly UserRepositoryInterface $users,
        private readonly TechnicianProfileRepositoryInterface $profiles,
        private readonly TradeRepositoryInterface $trades,
        private readonly MailerInterface $mailer,
        private readonly EmailTemplateRenderer $emailRenderer,
        private readonly SmtpSettingsRepositoryInterface $smtpSettings,
        private readonly CompanySettingsRepositoryInterface $companySettings,
        private readonly IntegrationBaseSettingsRepositoryInterface $baseSettings,
        private readonly ?MessagingSettingsRepositoryInterface $messagingSettings = null,
        private readonly ?MessagingTemplateRenderer $messagingRenderer = null,
        private readonly ?MessageSenderInterface $messageSender = null,
    ) {
    }

    /**
     * @return array{email: bool, channels: string[], errors: string[]}
     */
    public function notify(string $assignmentId, bool $reminder = false): array
    {
        $result = ['email' => false, 'channels' => [], 'errors' => []];
        $assignment = $this->assignments->findById($assignmentId);
        $rental = $assignment !== null ? $this->rentals->findById($assignment->rentalId) : null;
        $technician = $assignment !== null ? $this->users->findById($assignment->userId) : null;
        if ($assignment === null || $rental === null || $technician === null) {
            $result['errors'][] = 'Zuordnung, Veranstaltung oder Techniker nicht gefunden.';

            return $result;
        }

        [$emailKey, $messageKey] = match (true) {
            $assignment->status === RentalTechnicianStatus::Assigned || $assignment->status === RentalTechnicianStatus::Confirmed => [EmailTemplateKey::TechnicianAssigned, MessageTemplateKey::TechnicianAssigned],
            $reminder => [EmailTemplateKey::TechnicianReminder, MessageTemplateKey::TechnicianReminder],
            default => [EmailTemplateKey::TechnicianRequest, MessageTemplateKey::TechnicianRequest],
        };

        $plain = $this->variables($assignment, $rental, $technician->firstName ?: $technician->displayName(), false);
        $html = $this->variables($assignment, $rental, $technician->firstName ?: $technician->displayName(), true);

        // E-Mail mit Termineinladung
        try {
            $rendered = $this->emailRenderer->render($emailKey, $html);
            $smtp = $this->smtpSettings->get();
            $company = $this->companySettings->get();
            $description = $rental->eventName . "\n" . 'Zeitraum: ' . $plain['date_range'] . "\n" . $plain['schedule_line'] . 'Gewerke: ' . $plain['trades']
                . ($plain['link'] !== '' ? "\n" . $plain['link'] : '');
            $ics = TechnicianInvitationIcs::build($rental, $assignment, $technician, trim($smtp->fromAddress), trim($smtp->fromName) !== '' ? $smtp->fromName : ($company->name ?? ''), $description, $plain['link'] !== '' ? $plain['link'] : null);
            $attachments = [new MailAttachment('einladung.ics', 'text/calendar; method=REQUEST; charset=UTF-8', $ics)];
            if ($rendered->logoAttachment !== null) {
                $attachments[] = $rendered->logoAttachment;
            }
            $this->mailer->send($technician->email, $rendered->subject, $rendered->textBody, $attachments, $rendered->htmlBody, MailCategory::TechnicianNotification);
            $result['email'] = true;
        } catch (\Throwable $e) {
            $result['errors'][] = 'E-Mail: ' . $e->getMessage();
        }

        // Messenger
        $profile = $this->profiles->find($technician->id);
        $settings = $this->messagingSettings?->get();
        if ($profile !== null && $settings !== null && $this->messagingRenderer !== null && $this->messageSender !== null) {
            $phone = trim((string) $profile->phone);
            $telegram = trim((string) $profile->telegramChatId);
            $plain['schedule_line'] = rtrim($plain['schedule_line']) !== '' ? rtrim($plain['schedule_line']) . "\n" : '';
            if ($phone !== '' && $settings->isChannelReady(MessagingChannel::WhatsApp)) {
                try {
                    $resolved = $this->messagingRenderer->resolveWhatsappParameters($messageKey, $plain);
                    if ($resolved['templateName'] !== '') {
                        $this->messageSender->sendWhatsappTemplateMessage($phone, $resolved['templateName'], $resolved['languageCode'], $resolved['parameters']);
                        $result['channels'][] = MessagingChannel::WhatsApp->label();
                    }
                } catch (\Throwable $e) {
                    $result['errors'][] = 'WhatsApp: ' . $e->getMessage();
                }
            }
            if ($phone !== '' && $settings->isChannelReady(MessagingChannel::Signal)) {
                try {
                    $this->messageSender->sendSignalMessage($phone, $this->messagingRenderer->renderPlainText(MessagingChannel::Signal, $messageKey, $plain));
                    $result['channels'][] = MessagingChannel::Signal->label();
                } catch (\Throwable $e) {
                    $result['errors'][] = 'Signal: ' . $e->getMessage();
                }
            }
            if ($telegram !== '' && $settings->isChannelReady(MessagingChannel::Telegram)) {
                try {
                    $this->messageSender->sendTelegramMessage($telegram, $this->messagingRenderer->renderPlainText(MessagingChannel::Telegram, $messageKey, $plain));
                    $result['channels'][] = MessagingChannel::Telegram->label();
                } catch (\Throwable $e) {
                    $result['errors'][] = 'Telegram: ' . $e->getMessage();
                }
            }
        }

        $now = new DateTimeImmutable();
        if ($reminder) {
            $assignment->reminderCount++;
        }
        $assignment->lastNotifiedAt = $now;
        $assignment->updatedAt = $now;
        $this->assignments->save($assignment);

        return $result;
    }

    /** @return array<string, string> */
    private function variables(RentalTechnician $assignment, \App\Domain\Rental\Rental $rental, string $name, bool $html): array
    {
        $esc = static fn (string $v) => $html ? e($v) : $v;
        $br = $html ? '<br>' : "\n";
        $tradeNames = [];
        foreach ($assignment->tradeIds as $tradeId) {
            $trade = $this->trades->findById($tradeId);
            if ($trade !== null) {
                $tradeNames[] = $trade->name;
            }
        }
        $schedule = [];
        if ($rental->pickupAt !== null) {
            $schedule[] = 'Abholung ' . $rental->pickupAt->format('d.m.Y H:i');
        }
        if ($rental->returnAt !== null) {
            $schedule[] = 'Rückgabe ' . $rental->returnAt->format('d.m.Y H:i');
        }
        $baseUrl = rtrim((string) ($this->baseSettings->get()->baseUrl ?? ''), '/');
        $link = $baseUrl !== '' ? $baseUrl . '/veranstaltungen/' . $rental->id : '';
        $note = trim((string) $assignment->note);

        return [
            'technician_name' => $esc($name),
            'event_name' => $esc($rental->eventName),
            'date_range' => $rental->startDate->format('d.m.Y') . ($rental->endDate->format('Y-m-d') !== $rental->startDate->format('Y-m-d') ? ' – ' . $rental->endDate->format('d.m.Y') : ''),
            'schedule_line' => $schedule === [] ? '' : $esc(implode(', ', $schedule)) . $br,
            'location_line' => $rental->eventLocation !== null && trim($rental->eventLocation) !== '' ? 'Ort: ' . $esc($rental->eventLocation) . $br : '',
            'trades' => $tradeNames === [] ? '–' : $esc(implode(', ', $tradeNames)),
            'note_line' => $note === '' ? '' : ($html ? '<p>Hinweis: ' . e($note) . '</p>' : 'Hinweis: ' . $note . "\n"),
            'link' => $link,
        ];
    }
}
