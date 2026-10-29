<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Domain\Settings\PdfDesignSettingsRepositoryInterface;

/**
 * Währungsangabe hinter Beträgen auf den Dokument-PDFs (Migration 110):
 * " EUR" oder " €" je nach Einstellungen › PDF-Design. Wird einmal je
 * Instanz (= Request) aus den Einstellungen gelesen.
 */
final class PdfAmountFormatter
{
    private ?string $suffix = null;

    public function __construct(private readonly PdfDesignSettingsRepositoryInterface $settings)
    {
    }

    /** Anhängsel inkl. führendem Leerzeichen. */
    public function suffix(): string
    {
        return $this->suffix ??= $this->settings->get()->currencySuffix();
    }

    /** "1.234,56 EUR" bzw. "1.234,56 €". */
    public function format(float $amount): string
    {
        return number_format($amount, 2, ',', '.') . $this->suffix();
    }
}
