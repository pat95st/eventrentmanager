<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Rechtliche Texte (Vermietbedingungen/Impressum/Datenschutzerklärung),
 * Singleton-Zeile analog zu CompanySettings/SmtpSettings. Vermietbedingungen
 * sind ausschließlich als Markdown hinterlegbar (keine externe URL nötig);
 * Impressum und Datenschutzerklärung können wahlweise als Markdown-Text ODER
 * als externer Link gepflegt werden (siehe imprintMode/privacyMode).
 */
final class LegalSettings
{
    public const MODE_MARKDOWN = 'markdown';
    public const MODE_LINK = 'link';

    public function __construct(
        public ?string $rentalTermsMarkdown,
        public string $imprintMode,
        public ?string $imprintMarkdown,
        public ?string $imprintUrl,
        public string $privacyMode,
        public ?string $privacyMarkdown,
        public ?string $privacyUrl,
    ) {
    }
}
