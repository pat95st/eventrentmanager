<?php

declare(strict_types=1);

namespace App\Application\BankImport\Command;

use App\Application\Command\CommandInterface;

final class ConfirmBankImport implements CommandInterface
{
    /**
     * @param array<int, string> $selections rowIndex (aus der Zwischenablage) => Rechnungsnummer
     *        (vom Nutzer ggf. korrigiert); nur ausgewählte/angehakte Zeilen sind enthalten.
     */
    public function __construct(
        public readonly string $importId,
        public readonly array $selections,
        // Kontoführung (Migration 131): Umsätze dieser Zeilen (rowIndex) auf
        // das Konto $ledgerAccountId übernehmen; null = nicht übernehmen.
        public readonly ?string $ledgerAccountId = null,
        public readonly array $ledgerRows = [],
    ) {
    }
}
