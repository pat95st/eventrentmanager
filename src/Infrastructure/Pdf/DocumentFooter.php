<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Domain\Settings\BankAccount;
use App\Domain\Settings\BankAccountRepositoryInterface;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;

/**
 * Dreispaltige Fußzeile auf jeder Seite von Angebot, Auftragsbestätigung,
 * Rechnung und Mahnung (Nutzerwunsch):
 *
 *   Firmenname           | Tel.: …               | Bankverbindung:
 *   Straße               | Mail: …               | IBAN
 *   PLZ Ort              | Steuernummer / USt-IdNr. | Bank (BIC)
 *
 * Daten aus Einstellungen › Firma (Adresse, Telefon, E-Mail, Website),
 * Rechnungseinstellungen (Steuernummer, USt-IdNr.) und Bankverbindungen
 * (gewähltes Konto des Dokuments, sonst Standardkonto). Leere Spalten
 * entfallen. Wird nach dem Erzeugen aller Seiten gezeichnet, damit auch
 * Folgeseiten die Fußzeile tragen.
 */
final class DocumentFooter
{
    /** Oberkante der Fußzeile (pt vom unteren Seitenrand) — Inhalt muss darüber enden. */
    public const TOP_Y = 78.0;

    private const MARGIN = 40.0;
    private const FONT_SIZE = 7.5;
    private const LINE_HEIGHT = 10.0;

    public function __construct(
        private readonly CompanySettingsRepositoryInterface $companySettings,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
        private readonly BankAccountRepositoryInterface $bankAccounts,
    ) {
    }

    /**
     * @param BankAccount|null $bankAccount bereits aufgelöste Bankverbindung des Dokuments
     * @param string|null $bankAccountId alternativ die ID (z. B. Rechnung einer Mahnung)
     */
    public function drawOnAllPages(SimplePdfWriter $writer, ?BankAccount $bankAccount = null, ?string $bankAccountId = null): void
    {
        $columns = $this->columns($bankAccount ?? ($bankAccountId !== null ? $this->bankAccounts->findById($bankAccountId) : null));
        if ($columns === []) {
            return;
        }

        $pageWidth = $writer->pageWidth();
        $contentWidth = $pageWidth - 2 * self::MARGIN;
        $columnWidth = $contentWidth / count($columns);

        for ($page = 0; $page < $writer->pageCount(); $page++) {
            $writer->selectPage($page);
            $writer->line(self::MARGIN, self::TOP_Y, $pageWidth - self::MARGIN, self::TOP_Y, 0.5, '0.45 0.45 0.45');
            foreach ($columns as $index => $lines) {
                $x = self::MARGIN + $index * $columnWidth + 4;
                $y = self::TOP_Y - 12;
                foreach ($lines as $line) {
                    $writer->text($x, $y, $this->fit($writer, $line, $columnWidth - 8), self::FONT_SIZE, color: '0.25 0.25 0.25');
                    $y -= self::LINE_HEIGHT;
                }
            }
        }
    }

    /** @return list<list<string>> */
    private function columns(?BankAccount $bankAccount): array
    {
        $company = $this->companySettings->get();
        $invoiceSettings = $this->invoiceSettings->get();

        $address = array_values(array_filter([
            $company->name,
            $company->street,
            trim(($company->postalCode ?? '') . ' ' . ($company->city ?? '')),
        ], static fn (?string $v) => $v !== null && trim($v) !== ''));

        $contact = [];
        if ($this->filled($company->phone)) {
            $contact[] = 'Tel.: ' . trim($company->phone);
        }
        if ($this->filled($company->email)) {
            $contact[] = 'Mail: ' . trim($company->email);
        }
        if ($this->filled($company->website)) {
            $contact[] = 'Web: ' . trim($company->website);
        }
        if ($this->filled($invoiceSettings->taxNumber)) {
            $contact[] = 'Steuernummer: ' . trim($invoiceSettings->taxNumber);
        }
        if ($this->filled($invoiceSettings->vatId)) {
            $contact[] = 'USt-IdNr.: ' . trim($invoiceSettings->vatId);
        }

        $bankAccount ??= $this->bankAccounts->findDefault();
        $bank = [];
        if ($bankAccount !== null) {
            $bank = array_values(array_filter([
                'Bankverbindung:',
                $bankAccount->accountHolder !== null ? 'Inhaber: ' . $bankAccount->accountHolder : null,
                $bankAccount->formattedIban(),
                trim(($bankAccount->bankName ?? '') . ($bankAccount->bic !== null ? ($bankAccount->bankName !== null ? ' · ' : '') . 'BIC ' . $bankAccount->bic : '')),
            ], static fn (?string $v) => $v !== null && $v !== ''));
        } elseif ($this->filled($invoiceSettings->iban)) {
            // Fallback: alte Bankfelder der Rechnungseinstellungen.
            $bank = array_values(array_filter([
                'Bankverbindung:',
                trim($invoiceSettings->iban),
                trim(($invoiceSettings->bankName ?? '') . ($this->filled($invoiceSettings->bic) ? ' · BIC ' . $invoiceSettings->bic : '')),
            ], static fn (?string $v) => $v !== ''));
        }

        // Höchstens 5 Zeilen je Spalte (letzte Zeile bei ca. 9 mm vom
        // unteren Rand), damit Tel./Mail/Web + Steuernummer + USt-IdNr.
        // vollständig Platz finden und nichts in den Seitenrand läuft.
        return array_values(array_map(
            static fn (array $lines) => array_slice($lines, 0, 5),
            array_filter([$address, $contact, $bank], static fn (array $lines) => $lines !== []),
        ));
    }

    private function filled(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    /** Kürzt eine Zeile mit "…", falls sie breiter als die Spalte ist. */
    private function fit(SimplePdfWriter $writer, string $text, float $maxWidth): string
    {
        if ($writer->textWidth($text, self::FONT_SIZE) <= $maxWidth) {
            return $text;
        }
        while (mb_strlen($text) > 1 && $writer->textWidth($text . '…', self::FONT_SIZE) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return $text . '…';
    }
}
