<?php

declare(strict_types=1);

namespace App\Domain\Datev;

/**
 * Einstellungen für den DATEV-Export (Einstellungen → Integrationen → DATEV).
 *
 * Der Export erzeugt einen DATEV-Buchungsstapel im offiziellen
 * "DATEV-Format" (EXTF, CSV) mit Ausgangsrechnungen, Rechnungskorrekturen
 * und optional Eingangsbelegen, plus die zugehörigen Belege als PDF — alles
 * in einer ZIP-Datei, die der Steuerberater direkt in DATEV
 * Rechnungswesen bzw. DATEV Unternehmen online importiert. Siehe
 * App\Application\Datev\DatevExportBuilder.
 *
 * Kontenfelder (= NULL) fallen auf die Vorschlagskonten des gewählten
 * Kontenrahmens zurück (DatevChartOfAccounts::defaultAccount()).
 */
final class DatevSettings
{
    /** @param array<string, int|null> $accounts DatevAccountRole::value => Kontonummer (NULL = Vorschlag des Kontenrahmens) */
    public function __construct(
        public bool $enabled = false,
        // Beraternummer (1001–9999999) und Mandantennummer (1–99999) aus DATEV.
        public ?int $consultantNumber = null,
        public ?int $clientNumber = null,
        public DatevChartOfAccounts $chartOfAccounts = DatevChartOfAccounts::Skr03,
        // Sachkontenlänge (4–8); Personenkonten (Debitoren/Kreditoren) sind eine Stelle länger.
        public int $accountLength = 4,
        // Erster Monat des Wirtschaftsjahres (1 = Januar).
        public int $fiscalYearStartMonth = 1,
        public DatevDebtorMode $debtorMode = DatevDebtorMode::Collective,
        // Debitoren-Sammelkonto bzw. bei Einzelkonten der Startwert, zu dem die laufende
        // Nummer der Kundennummer addiert wird. NULL = 10000 (bei Sachkontenlänge 4).
        public ?int $debtorAccount = null,
        // Kreditoren-Sammelkonto für Eingangsbelege. NULL = 70000 (bei Sachkontenlänge 4).
        public ?int $creditorAccount = null,
        public array $accounts = [],
        // Optionaler BU-Schlüssel für die Vorsteuer auf Eingangsbelegen (z. B. "9" = 19 % VSt).
        // Leer = ohne Steuerschlüssel, der Steuerberater ordnet die Vorsteuer zu.
        public ?string $receiptTaxKey = null,
        public bool $includeReceipts = true,
        public bool $includeDocuments = true,
        // E-Mail-Adresse des Steuerberaters (bzw. DATEV-Upload-Adresse) für den Versand.
        public ?string $advisorEmail = null,
        public bool $autoSendEnabled = false,
        public DatevExportInterval $interval = DatevExportInterval::Monthly,
        // Versand frühestens an diesem Tag des Folgemonats (1–28), damit spät
        // eintreffende Belege noch erfasst werden können.
        public int $sendDay = 5,
    ) {
    }

    /** Konto für eine Rolle — eigenes Konto oder Vorschlag des Kontenrahmens (auf Sachkontenlänge aufgefüllt). */
    public function account(DatevAccountRole $role): int
    {
        return $this->accounts[$role->value] ?? $this->padAccount($this->chartOfAccounts->defaultAccount($role));
    }

    public function isCustomAccount(DatevAccountRole $role): bool
    {
        return isset($this->accounts[$role->value]);
    }

    public function defaultAccount(DatevAccountRole $role): int
    {
        return $this->padAccount($this->chartOfAccounts->defaultAccount($role));
    }

    public function effectiveDebtorAccount(): int
    {
        return $this->debtorAccount ?? $this->defaultDebtorAccount();
    }

    public function defaultDebtorAccount(): int
    {
        return 10 ** $this->accountLength;
    }

    public function effectiveCreditorAccount(): int
    {
        return $this->creditorAccount ?? $this->defaultCreditorAccount();
    }

    public function defaultCreditorAccount(): int
    {
        return 7 * 10 ** $this->accountLength;
    }

    /**
     * Debitorenkonto für einen Kunden. Im Einzelkonten-Modus wird die
     * laufende Nummer aus der Kundennummer (alle Ziffern, z. B. "K-00042"
     * → 42) auf den Startwert addiert; passt das Ergebnis nicht in den
     * Debitorenbereich (10000–69999 bei Sachkontenlänge 4), liefert die
     * Methode NULL und der Aufrufer weicht auf das Sammelkonto aus.
     */
    public function debtorAccountFor(?string $customerNumber): ?int
    {
        if ($this->debtorMode === DatevDebtorMode::Collective) {
            return $this->effectiveDebtorAccount();
        }

        $digits = preg_replace('/\D+/', '', (string) $customerNumber);
        if ($digits === '' || $digits === null || strlen($digits) > 9) {
            return null;
        }

        $account = $this->effectiveDebtorAccount() + (int) $digits;

        return $this->isDebtorAccount($account) ? $account : null;
    }

    public function isDebtorAccount(int $account): bool
    {
        return $account >= 10 ** $this->accountLength && $account < 7 * 10 ** $this->accountLength;
    }

    public function isCreditorAccount(int $account): bool
    {
        return $account >= 7 * 10 ** $this->accountLength && $account < 10 ** ($this->accountLength + 1);
    }

    public function isGeneralLedgerAccount(int $account): bool
    {
        return $account >= 1 && $account < 10 ** $this->accountLength;
    }

    /** DATEV füllt Sachkonten rechts mit Nullen auf, z. B. 8400 → 84000 bei Sachkontenlänge 5. */
    public function padAccount(int $fourDigitAccount): int
    {
        return $fourDigitAccount * 10 ** max(0, $this->accountLength - 4);
    }

    /** Beginn des Wirtschaftsjahres, in das $date fällt. */
    public function fiscalYearStartFor(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $year = (int) $date->format('Y');
        if ((int) $date->format('n') < $this->fiscalYearStartMonth) {
            $year--;
        }

        return $date->setDate($year, $this->fiscalYearStartMonth, 1)->setTime(0, 0);
    }

    /**
     * Zerlegt einen Zeitraum in Abschnitte, die jeweils innerhalb eines
     * Wirtschaftsjahres liegen (ein DATEV-Buchungsstapel darf das
     * Wirtschaftsjahr nicht überschreiten).
     *
     * @return list<array{fiscalYearStart: \DateTimeImmutable, from: \DateTimeImmutable, to: \DateTimeImmutable}>
     */
    public function splitByFiscalYear(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $from = $from->setTime(0, 0);
        $to = $to->setTime(0, 0);
        $chunks = [];

        while ($from <= $to) {
            $fiscalYearStart = $this->fiscalYearStartFor($from);
            $fiscalYearEnd = $fiscalYearStart->modify('+1 year -1 day');
            $chunkTo = $to < $fiscalYearEnd ? $to : $fiscalYearEnd;
            $chunks[] = ['fiscalYearStart' => $fiscalYearStart, 'from' => $from, 'to' => $chunkTo];
            $from = $chunkTo->modify('+1 day');
        }

        return $chunks;
    }

    /** Fehlen Pflichtangaben für einen Export? Liefert eine Liste lesbarer Hinweise. */
    public function missingRequirements(): array
    {
        $missing = [];
        if ($this->consultantNumber === null) {
            $missing[] = 'Beraternummer';
        }
        if ($this->clientNumber === null) {
            $missing[] = 'Mandantennummer';
        }

        return $missing;
    }
}
