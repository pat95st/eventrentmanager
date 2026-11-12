<?php

declare(strict_types=1);

namespace App\Application\Datev;

use App\Application\Invoice\Query\GetInvoice;
use App\Application\Invoice\Query\GetInvoiceHandler;
use App\Application\InvoiceCorrection\Query\GetInvoiceCorrection;
use App\Application\InvoiceCorrection\Query\GetInvoiceCorrectionHandler;
use App\Domain\Datev\DatevAccountRole;
use App\Domain\Datev\DatevDebtorMode;
use App\Domain\Datev\DatevSettings;
use App\Domain\Datev\DatevSettingsRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\InvoiceCorrection\InvoiceCorrectionRepositoryInterface;
use App\Domain\InvoiceCorrection\InvoiceCorrectionStatus;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Infrastructure\Datev\DatevBuchungsstapelWriter;
use DateTimeImmutable;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Erzeugt das DATEV-Paket für einen Zeitraum: eine ZIP-Datei mit
 *  - EXTF_Buchungsstapel_<von>_<bis>.csv (je Wirtschaftsjahr eine Datei),
 *  - den Belegen als PDF/Originaldatei (optional, siehe DatevSettings::$includeDocuments),
 *  - Hinweise.txt mit Zusammenfassung, Kontenzuordnung und Auffälligkeiten.
 *
 * Erfasst werden (Sollversteuerung, nach Belegdatum):
 *  - alle finalisierten Ausgangsrechnungen mit Rechnungsdatum im Zeitraum,
 *  - alle ausgestellten Rechnungskorrekturen/Stornorechnungen mit Datum im Zeitraum,
 *  - optional alle Eingangsbelege mit Belegdatum im Zeitraum.
 */
final class DatevExportBuilder
{
    public function __construct(
        private readonly DatevSettingsRepositoryInterface $settingsRepository,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly GetInvoiceHandler $getInvoiceHandler,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
        private readonly InvoiceCorrectionRepositoryInterface $corrections,
        private readonly GetInvoiceCorrectionHandler $getInvoiceCorrectionHandler,
        private readonly ReceiptRepositoryInterface $receipts,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly DatevDocumentSourceInterface $documents,
        private readonly DatevBuchungsstapelWriter $writer = new DatevBuchungsstapelWriter(),
    ) {
    }

    public function build(DateTimeImmutable $from, DateTimeImmutable $to): DatevExportPackage
    {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);
        $settings = $this->settingsRepository->get();

        $this->validate($settings, $from, $to);

        $factory = new DatevBookingFactory($settings);
        $invoiceSettings = $this->invoiceSettings->get();

        /** @var DatevBatch[] $batches */
        $batches = array_map(
            static fn (array $chunk) => new DatevBatch($chunk['fiscalYearStart'], $chunk['from'], $chunk['to']),
            $settings->splitByFiscalYear($from, $to),
        );
        $addBooking = static function (?\App\Domain\Datev\DatevBooking $booking) use ($batches): bool {
            if ($booking === null) {
                return false;
            }
            foreach ($batches as $batch) {
                if ($booking->documentDate >= $batch->from && $booking->documentDate <= $batch->to) {
                    $batch->bookings[] = $booking;

                    return true;
                }
            }

            return false;
        };

        $files = [];
        $warnings = [];
        $invoiceCount = 0;
        $correctionCount = 0;
        $receiptCount = 0;

        foreach ($this->invoices->findFinalizedBetween($from, $to) as $invoice) {
            $detail = $this->getInvoiceHandler->handle(new GetInvoice($invoice->id));
            $totals = $detail->totals($invoiceSettings);
            $booking = $factory->forInvoice(
                $invoice,
                $detail->customer,
                round($totals->grossTotal, 2),
                $invoice->isSmallBusiness ?? $invoiceSettings->isSmallBusiness,
                $invoice->vatRatePercent ?? $invoiceSettings->vatRatePercent,
            );
            if (!$addBooking($booking)) {
                continue;
            }
            $invoiceCount++;

            if ($settings->includeDocuments) {
                $this->collect($files, $warnings, 'Belege/Ausgangsrechnungen/', 'Rechnung ' . $invoice->invoiceNumber, fn () => $this->documents->invoicePdf($detail));
            }
        }

        foreach ($this->corrections->findIssued() as $correction) {
            if ($correction->status !== InvoiceCorrectionStatus::Issued || $correction->correctionDate === null) {
                continue;
            }
            $date = $correction->correctionDate->setTime(0, 0);
            if ($date < $from || $date > $to) {
                continue;
            }

            $detail = $this->getInvoiceCorrectionHandler->handle(new GetInvoiceCorrection($correction->id));
            $booking = $factory->forCorrection($correction, $detail->invoice->invoice, $detail->invoice->customer);
            if (!$addBooking($booking)) {
                continue;
            }
            $correctionCount++;

            if ($settings->includeDocuments) {
                $this->collect($files, $warnings, 'Belege/Rechnungskorrekturen/', $correction->title(), fn () => $this->documents->correctionPdf($detail));
            }
        }

        if ($settings->includeReceipts) {
            $supplierCache = [];
            foreach ($this->receipts->findByReceiptDateBetween($from, $to) as $receipt) {
                $supplierCache[$receipt->supplierId] ??= $this->suppliers->findById($receipt->supplierId);
                $supplier = $supplierCache[$receipt->supplierId];

                if (!$addBooking($factory->forReceipt($receipt, $supplier))) {
                    continue;
                }
                $receiptCount++;

                if ($settings->includeDocuments) {
                    $label = 'Beleg ' . ($receipt->receiptNumber ?? $receipt->originalFilename);
                    $this->collect($files, $warnings, 'Belege/Eingangsbelege/', $label, function () use ($receipt) {
                        $file = $this->documents->receiptFile($receipt);
                        if ($file === null) {
                            throw new RuntimeException('Datei nicht gefunden');
                        }

                        return $file;
                    });
                }
            }
        }

        $warnings = array_merge($factory->warnings(), $warnings);

        $csvFiles = [];
        foreach ($batches as $batch) {
            if ($batch->bookings === []) {
                continue;
            }
            $label = 'Verleih ' . $batch->from->format('m/Y') . ($batch->from->format('Ym') !== $batch->to->format('Ym') ? '-' . $batch->to->format('m/Y') : '');
            $name = 'EXTF_Buchungsstapel_' . $batch->from->format('Ymd') . '_' . $batch->to->format('Ymd') . '.csv';
            $csvFiles[$name] = $this->writer->write($settings, $batch->fiscalYearStart, $batch->from, $batch->to, $label, $batch->bookings);
        }

        $documentCount = count($files);
        $summary = $this->summary($settings, $from, $to, $invoiceCount, $correctionCount, $receiptCount, $documentCount, $warnings);

        $zip = $this->zip($csvFiles + ['Hinweise.txt' => $summary] + $files);
        $filename = 'DATEV_Export_' . $from->format('Y-m-d') . '_bis_' . $to->format('Y-m-d') . '.zip';

        return new DatevExportPackage($from, $to, $filename, $zip, $invoiceCount, $correctionCount, $receiptCount, $documentCount, $warnings);
    }

    private function validate(DatevSettings $settings, DateTimeImmutable $from, DateTimeImmutable $to): void
    {
        $errors = [];
        $missing = $settings->missingRequirements();
        if ($missing !== []) {
            $errors['datev'] = 'Bitte zuerst in den DATEV-Einstellungen hinterlegen: ' . implode(', ', $missing) . '.';
        }
        if ($from > $to) {
            $errors['datev_period'] = 'Das Startdatum muss vor dem Enddatum liegen.';
        } elseif ($from->modify('+3 years') < $to) {
            $errors['datev_period'] = 'Der Zeitraum darf höchstens drei Jahre umfassen.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Erzeugt eine Belegdatei; Fehler (z. B. fehlende Datei) brechen den
     * Export nicht ab, sondern landen als Hinweis in Hinweise.txt.
     *
     * @param array<string, string> $files
     * @param string[] $warnings
     * @param callable(): array{0: string, 1: string} $producer
     */
    private function collect(array &$files, array &$warnings, string $directory, string $label, callable $producer): void
    {
        try {
            [$name, $content] = $producer();
        } catch (Throwable $e) {
            $warnings[] = "{$label}: Belegdatei konnte nicht beigefügt werden ({$e->getMessage()}).";

            return;
        }

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? 'Beleg';
        $path = $directory . $name;
        $counter = 2;
        while (isset($files[$path])) {
            $dot = strrpos($name, '.');
            $path = $directory . ($dot !== false ? substr($name, 0, $dot) . '-' . $counter . substr($name, $dot) : $name . '-' . $counter);
            $counter++;
        }
        $files[$path] = $content;
    }

    /** @param string[] $warnings */
    private function summary(DatevSettings $settings, DateTimeImmutable $from, DateTimeImmutable $to, int $invoiceCount, int $correctionCount, int $receiptCount, int $documentCount, array $warnings): string
    {
        $lines = [
            'DATEV-Export Materialverleih',
            str_repeat('=', 40),
            'Zeitraum:           ' . $from->format('d.m.Y') . ' – ' . $to->format('d.m.Y'),
            'Erstellt:           ' . (new DateTimeImmutable())->format('d.m.Y H:i'),
            'Berater/Mandant:    ' . $settings->consultantNumber . ' / ' . $settings->clientNumber,
            'Kontenrahmen:       ' . $settings->chartOfAccounts->label() . ', Sachkontenlänge ' . $settings->accountLength,
            '',
            'Ausgangsrechnungen: ' . $invoiceCount,
            'Korrekturen/Stornos: ' . $correctionCount,
            'Eingangsbelege:     ' . ($settings->includeReceipts ? (string) $receiptCount : 'nicht exportiert'),
            'Belegdateien:       ' . ($settings->includeDocuments ? (string) $documentCount : 'nicht beigefügt'),
            '',
        ];

        if ($invoiceCount + $correctionCount + $receiptCount === 0) {
            $lines[] = 'Im Zeitraum gab es keine Buchungen — es ist daher kein Buchungsstapel enthalten.';
            $lines[] = '';
        }

        $lines[] = 'Kontenzuordnung';
        $lines[] = str_repeat('-', 40);
        $lines[] = 'Debitoren: ' . ($settings->debtorMode === DatevDebtorMode::Collective
            ? 'Sammelkonto ' . $settings->effectiveDebtorAccount()
            : 'Einzelkonten ab ' . $settings->effectiveDebtorAccount() . ' + laufende Nummer der Kundennummer');
        $lines[] = 'Kreditoren: Sammelkonto ' . $settings->effectiveCreditorAccount();
        foreach (DatevAccountRole::cases() as $role) {
            $lines[] = $role->label() . ': ' . $settings->account($role);
        }
        if ($settings->receiptTaxKey !== null && $settings->receiptTaxKey !== '') {
            $lines[] = 'BU-Schlüssel Eingangsbelege: ' . $settings->receiptTaxKey;
        }
        $lines[] = '';
        $lines[] = 'Buchungslogik: Ausgangsrechnungen Debitor an Erlöskonto (brutto, Automatikkonten),';
        $lines[] = 'Eingangsbelege Aufwandskonto an Kreditor (brutto). Der Stapel ist nicht festgeschrieben.';
        $lines[] = 'Import in DATEV Rechnungswesen/Kanzlei-Rechnungswesen über den Import von Buchungsdaten im DATEV-Format (CSV).';

        if ($warnings !== []) {
            $lines[] = '';
            $lines[] = 'Bitte prüfen';
            $lines[] = str_repeat('-', 40);
            foreach ($warnings as $warning) {
                $lines[] = '- ' . $warning;
            }
        }

        return "\u{FEFF}" . implode("\r\n", $lines) . "\r\n";
    }

    /** @param array<string, string> $files */
    private function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'datev');
        if ($path === false) {
            throw new RuntimeException('Temporäre Datei für den DATEV-Export konnte nicht angelegt werden.');
        }

        try {
            $zip = new ZipArchive();
            if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('ZIP-Datei für den DATEV-Export konnte nicht erstellt werden.');
            }
            foreach ($files as $name => $content) {
                $zip->addFromString($name, $content);
            }
            $zip->close();

            $content = file_get_contents($path);
            if ($content === false) {
                throw new RuntimeException('ZIP-Datei für den DATEV-Export konnte nicht gelesen werden.');
            }

            return $content;
        } finally {
            @unlink($path);
        }
    }
}
