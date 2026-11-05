<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Domain\Settings\InvoiceSettings;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;

/**
 * Hinweistext zur Umsatzsteuerbefreiung für alle Dokumente (Migration 133):
 * eigener Text aus den Steuer-Einstellungen, sonst der Standardtext nach
 * § 19 UStG. Statisch (in bootstrap.php mit dem Repository verbunden), damit
 * auch PDF-Generatoren ohne InvoiceSettings-Parameter ihn verwenden können.
 */
final class TaxNote
{
    private static ?InvoiceSettingsRepositoryInterface $repository = null;
    private static ?string $cached = null;

    public static function useRepository(InvoiceSettingsRepositoryInterface $repository): void
    {
        self::$repository = $repository;
        self::$cached = null;
    }

    public static function smallBusiness(?InvoiceSettings $settings = null): string
    {
        if ($settings !== null) {
            return $settings->smallBusinessNoteText();
        }
        if (self::$cached === null) {
            try {
                self::$cached = self::$repository?->get()->smallBusinessNoteText() ?? InvoiceSettings::DEFAULT_SMALL_BUSINESS_NOTE;
            } catch (\Throwable) {
                self::$cached = InvoiceSettings::DEFAULT_SMALL_BUSINESS_NOTE;
            }
        }

        return self::$cached;
    }
}
