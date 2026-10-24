<?php

declare(strict_types=1);

namespace App\Infrastructure\Migrations;

use RuntimeException;
use Throwable;

/**
 * Eine Migrationsdatei ist beim Einspielen fehlgeschlagen (siehe
 * MigrationRunner::applyAll()). Trägt das bis dahin aufgelaufene Log mit,
 * damit der Aufrufer (Installer/CLI) anzeigen kann, welche Dateien bereits
 * erfolgreich angewendet wurden, bevor diese hier fehlschlug.
 */
final class MigrationFailedException extends RuntimeException
{
    /** @param array<int, array{file: string, status: string}> $log */
    public function __construct(
        public readonly string $migrationFile,
        string $driverMessage,
        public readonly array $log,
        ?Throwable $previous = null,
    ) {
        parent::__construct("Migration \"{$migrationFile}\" fehlgeschlagen: {$driverMessage}", previous: $previous);
    }
}
