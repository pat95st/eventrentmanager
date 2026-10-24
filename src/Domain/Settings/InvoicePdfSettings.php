<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Admin-bearbeitbarer Einleitungs-/Schlusstext im eigentlichen Rechnungs-PDF
 * (siehe App\Infrastructure\Pdf\InvoicePdfGenerator), Singleton-Zeile analog
 * zu OfferPdfSettings — bewusst als eigene, identisch aufgebaute Klasse
 * gehalten statt Wiederverwendung von OfferPdfSettings, damit Angebots- und
 * Rechnungsvorlage unabhängig voneinander gepflegt werden können. Beide
 * Felder sind ein bereits bereinigtes HTML-Fragment, siehe
 * OfferPdfSettings-Klassendoc für die Begründung (Migration 064, weg von
 * Markdown). NICHT zu verwechseln mit der "Rechnung per E-Mail"-Vorlage
 * (siehe EmailTemplateKey::InvoicePdf), die den Text der E-Mail selbst
 * steuert.
 */
final class InvoicePdfSettings
{
    public function __construct(
        public ?string $introHtml,
        public ?string $closingHtml,
    ) {
    }
}
