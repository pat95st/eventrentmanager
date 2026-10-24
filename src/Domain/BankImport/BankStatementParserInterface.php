<?php

declare(strict_types=1);

namespace App\Domain\BankImport;

/**
 * Gemeinsame Schnittstelle für die formatspezifischen Parser (MT940,
 * CAMT.053). Eine Implementierung wandelt den Rohinhalt der hochgeladenen
 * Datei in eine Liste von BankTransaction-Wertobjekten um.
 */
interface BankStatementParserInterface
{
    /**
     * @return BankTransaction[]
     *
     * @throws UnsupportedBankStatementException wenn der Inhalt nicht im
     *         erwarteten Format vorliegt oder keine Buchungen enthält.
     */
    public function parse(string $content): array;
}
