<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * Zugangsdaten + Aktivierungsschalter für alle drei Messenger-Dienste,
 * als Singleton-Zeile analog zu App\Domain\Settings\MailboxSettings/
 * SmtpSettings. Bewusst EIN Entity für alle drei Kanäle (statt drei
 * getrennter Settings-Klassen) — es gibt ohnehin nur eine gemeinsame
 * Einstellungsseite (/einstellungen/messenger) dafür.
 *
 * WICHTIG: Dies ist eine EIGENSTÄNDIGE WhatsApp-Business-Verbindung für den
 * Kunden-Nachrichtenversand (Meta Cloud API, Graph-Endpunkt
 * "/{phone_number_id}/messages") — NICHT dieselbe wie
 * App\Domain\Settings\WhatsappBusinessSettings, die ausschließlich den
 * Produktkatalog (Commerce Manager) für die Artikel-Veröffentlichung
 * steuert. Beide können, müssen aber nicht, mit demselben Meta-Access-Token
 * arbeiten — bewusst getrennt gehalten, damit eine der beiden Funktionen
 * nicht versehentlich die Zugangsdaten der anderen überschreibt.
 */
final class MessagingSettings
{
    public function __construct(
        public bool $whatsappEnabled = false,
        public ?string $whatsappPhoneNumberId = null,
        public ?string $whatsappAccessToken = null,
        public ?string $whatsappBusinessAccountId = null,

        public bool $telegramEnabled = false,
        public ?string $telegramBotToken = null,

        public bool $signalEnabled = false,
        public ?string $signalApiBaseUrl = null,
        public ?string $signalSenderNumber = null,
    ) {
    }

    public function isWhatsappConfigured(): bool
    {
        return $this->notBlank($this->whatsappPhoneNumberId) && $this->notBlank($this->whatsappAccessToken);
    }

    public function isTelegramConfigured(): bool
    {
        return $this->notBlank($this->telegramBotToken);
    }

    public function isSignalConfigured(): bool
    {
        return $this->notBlank($this->signalApiBaseUrl) && $this->notBlank($this->signalSenderNumber);
    }

    public function isChannelReady(MessagingChannel $channel): bool
    {
        return match ($channel) {
            MessagingChannel::WhatsApp => $this->whatsappEnabled && $this->isWhatsappConfigured(),
            MessagingChannel::Telegram => $this->telegramEnabled && $this->isTelegramConfigured(),
            MessagingChannel::Signal => $this->signalEnabled && $this->isSignalConfigured(),
        };
    }

    private function notBlank(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }
}
