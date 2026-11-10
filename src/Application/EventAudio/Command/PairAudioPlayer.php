<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

/**
 * Abspielgerät per Kopplungs-Code an eine Veranstaltung koppeln (Migration 141).
 * Sendet das Gerät bereits einen gültigen Geräte-Token mit, wird dieses Gerät
 * umgehängt statt ein neues anzulegen.
 */
final class PairAudioPlayer implements CommandInterface
{
    public function __construct(
        public readonly string $code,
        public readonly ?string $deviceName,
        public readonly ?string $existingToken,
        public readonly ?string $ip,
        public readonly ?string $version = null,
    ) {
    }
}
