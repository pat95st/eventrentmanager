<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Domain\Mail\MailCategory;

final class SmtpSettings
{
    /**
     * @param string[]|null $archiveCategories Werte von MailCategory::value,
     *        auf die die Archivierung eingeschränkt wird (siehe
     *        shouldArchive()). null oder [] = alle Kategorien archivieren
     *        (Fallback für "noch nie konfiguriert").
     */
    public function __construct(
        public string $host,
        public int $port,
        public SmtpEncryption $encryption,
        public ?string $username,
        public ?string $password,
        public string $fromAddress,
        public string $fromName,
        // Beide optional: reply-to weicht nur ab, wenn Antworten NICHT an
        // die Absenderadresse gehen sollen; archiveBcc erhält bei JEDER
        // ausgehenden Mail eine stille Kopie (siehe SmtpMailer::send()),
        // sofern nicht durch archiveCategories weiter eingeschränkt.
        public ?string $replyToAddress = null,
        public ?string $archiveBccAddress = null,
        public ?array $archiveCategories = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->host) !== '' && trim($this->fromAddress) !== '';
    }

    /**
     * Ob eine Mail dieser Kategorie bei gesetzter Archivierungsadresse
     * tatsächlich per BCC archiviert werden soll (siehe SmtpMailer::send()).
     */
    public function shouldArchive(MailCategory $category): bool
    {
        if ($this->archiveBccAddress === null || trim($this->archiveBccAddress) === '') {
            return false;
        }

        // Nur null (noch nie konfiguriert, siehe Migration 029/Repository)
        // bedeutet "alle archivieren". Ein explizit gespeichertes leeres
        // Array (Administrator hat alle Kontrollkästchen abgewählt)
        // bedeutet bewusst "keine Kategorie archivieren" — siehe
        // UpdateSmtpSettingsHandler, wo genau dieser Unterschied zwischen
        // "nichts übermittelt" (bleibt null) und "nichts ausgewählt" ([])
        // hergestellt wird.
        return $this->archiveCategories === null || in_array($category->value, $this->archiveCategories, true);
    }
}
