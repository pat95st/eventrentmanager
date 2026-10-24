<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

/**
 * Trägt ein Feld => Fehlermeldung-Array, damit die View gezielt neben dem
 * jeweiligen Formularfeld einen Fehler anzeigen kann.
 */
final class ValidationException extends RuntimeException
{
    /** @param array<string, string> $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Validierungsfehler: ' . implode(', ', $errors));
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
