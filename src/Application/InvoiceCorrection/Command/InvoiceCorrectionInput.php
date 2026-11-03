<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Command;

use App\Domain\InvoiceCorrection\InvoiceCorrectionLine;
use App\Shared\Uuid;

/**
 * Formulareingaben einer Rechnungskorrektur. Positionen kommen roh aus dem
 * Formular (lines[n][description|quantity|unit_price]); Beträge mit Komma
 * oder Punkt, negative Einzelpreise sind erlaubt (Gutschrift).
 */
final class InvoiceCorrectionInput
{
    /** @param array<int|string, array{description?: string, quantity?: string, unit_price?: string}> $rawLines */
    public function __construct(
        public readonly string $reason,
        public readonly ?string $note,
        public readonly array $rawLines = [],
    ) {
    }

    /**
     * @param array<string, string> $errors
     * @return InvoiceCorrectionLine[]
     */
    public function parseLines(string $correctionId, array &$errors): array
    {
        $lines = [];
        $sort = 0;
        foreach (array_values($this->rawLines) as $index => $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $description = trim((string) ($raw['description'] ?? ''));
            $rawQuantity = trim((string) ($raw['quantity'] ?? ''));
            $rawPrice = trim((string) ($raw['unit_price'] ?? ''));
            if ($description === '' && $rawPrice === '' && ($rawQuantity === '' || $rawQuantity === '1')) {
                continue; // leere Zeile ignorieren
            }
            $number = $index + 1;
            $quantity = filter_var(self::normalize($rawQuantity === '' ? '1' : $rawQuantity), FILTER_VALIDATE_FLOAT);
            $price = filter_var(self::normalize($rawPrice), FILTER_VALIDATE_FLOAT);
            if ($description === '') {
                $errors['lines'] = "Position {$number}: Bitte eine Bezeichnung angeben.";
            } elseif (mb_strlen($description) > 500) {
                $errors['lines'] = "Position {$number}: Die Bezeichnung ist zu lang (max. 500 Zeichen).";
            } elseif ($quantity === false || $quantity <= 0 || $quantity > 99999999) {
                $errors['lines'] = "Position {$number}: Die Menge muss eine Zahl größer 0 sein.";
            } elseif ($rawPrice === '' || $price === false || abs($price) > 9999999999) {
                $errors['lines'] = "Position {$number}: Bitte einen gültigen Einzelpreis angeben (negativ für Gutschriften).";
            } else {
                $lines[] = new InvoiceCorrectionLine(Uuid::v4(), $correctionId, $sort++, $description, number_format($quantity, 2, '.', ''), number_format($price, 2, '.', ''));
            }
        }

        return $lines;
    }

    /** "1.234,56" → "1234.56", "12,5" → "12.5", "-3.20" bleibt. */
    private static function normalize(string $value): string
    {
        $value = str_replace([' ', '€'], '', $value);
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }

        return $value;
    }
}
