<?php

declare(strict_types=1);

namespace App\Application\Backup\Command;

use App\Application\Command\CommandInterface;

/** Raw form values of the automatic backup settings (Einstellungen → Sicherung), validated by the handler. */
final class UpdateBackupSettings implements CommandInterface
{
    /** @param list<string> $storageTargetIds */
    public function __construct(
        public readonly bool $enabled,
        public readonly array $storageTargetIds,
        public readonly ?string $targetPath,
        public readonly ?string $interval,
        // "HH:MM"
        public readonly ?string $runTime,
        public readonly ?string $weekday,
        public readonly ?string $monthDay,
    ) {
    }
}
