<?php

declare(strict_types=1);

namespace App\Domain\BankImport;

/**
 * Erkennt anhand des Rohinhalts einer hochgeladenen Datei, ob es sich um
 * ein MT940- oder ein CAMT.053-Kontoauszug handelt. Rein inhaltsbasiert
 * (nicht dateinamensbasiert), da Banken unterschiedliche Dateiendungen
 * verwenden (.sta, .mt940, .txt für MT940; .xml für CAMT.053).
 */
final class BankStatementFormatDetector
{
    public static function detect(string $content): ?BankStatementFormat
    {
        $trimmed = ltrim($content);

        if ($trimmed !== '' && $trimmed[0] === '<') {
            if (preg_match('/<([a-zA-Z0-9]+:)?BkToCstmrStmt\b/', $content) === 1) {
                return BankStatementFormat::Camt053;
            }

            return null;
        }

        if (preg_match('/^:2[05]:/m', $content) === 1 && preg_match('/^:6[12]:/m', $content) === 1) {
            return BankStatementFormat::Mt940;
        }

        return null;
    }
}
