<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Admin-bearbeitbarer Einleitungs-/Schlusstext im eigentlichen Angebots-PDF
 * (siehe App\Infrastructure\Pdf\OfferPdfGenerator), Singleton-Zeile analog
 * zu LegalSettings/CompanySettings. Beide Felder sind ein bereits vom
 * WYSIWYG-Editor erzeugtes, serverseitig bereinigtes HTML-Fragment (siehe
 * App\Infrastructure\RichText\HtmlSanitizer) — bewusst kein Markdown mehr
 * (siehe Migration 064): der contenteditable-Editor in
 * resources/views/settings/offer-pdf-template.php liefert ohnehin HTML,
 * eine Zwischenübersetzung nach Markdown und zurück war nur Quelle
 * zusätzlicher Ungenauigkeit. NICHT zu verwechseln mit der "Angebot per
 * E-Mail"-Vorlage (siehe EmailTemplateKey::OfferPdf), die den Text der
 * E-Mail selbst steuert.
 */
final class OfferPdfSettings
{
    public function __construct(
        public ?string $introHtml,
        public ?string $closingHtml,
    ) {
    }
}
