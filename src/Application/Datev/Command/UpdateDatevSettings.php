<?php

declare(strict_types=1);

namespace App\Application\Datev\Command;

use App\Application\Command\CommandInterface;

/** Rohwerte aus dem Formular (Einstellungen → Integrationen → DATEV), Validierung im Handler. */
final class UpdateDatevSettings implements CommandInterface
{
    /** @param array<string, string|null> $accounts DatevAccountRole::value => Eingabe (leer = Vorschlag des Kontenrahmens) */
    public function __construct(
        public readonly bool $enabled,
        public readonly ?string $consultantNumber,
        public readonly ?string $clientNumber,
        public readonly ?string $chartOfAccounts,
        public readonly ?string $accountLength,
        public readonly ?string $fiscalYearStartMonth,
        public readonly ?string $debtorMode,
        public readonly ?string $debtorAccount,
        public readonly ?string $creditorAccount,
        public readonly array $accounts,
        public readonly ?string $receiptTaxKey,
        public readonly bool $includeReceipts,
        public readonly bool $includeDocuments,
        public readonly ?string $advisorEmail,
        public readonly bool $autoSendEnabled,
        public readonly ?string $interval,
        public readonly ?string $sendDay,
    ) {
    }
}
