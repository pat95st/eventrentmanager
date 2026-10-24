<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

/**
 * Für Löschversuche, die an fachlichen Abhängigkeiten scheitern
 * (z. B. Kategorie mit Unterkategorien, Hersteller mit Artikeln).
 */
final class ConflictException extends RuntimeException
{
}
