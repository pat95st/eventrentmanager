<?php

declare(strict_types=1);

namespace App\Domain\DigitalStagebox;

/** Digitales Audioprotokoll, z. B. „Dante“ oder „AES50“ (Migration 152, Einstellungen › Digitale Protokolle). */
final class DigitalProtocol
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public int $sortOrder = 0,
        // Migration 155: IP-based protocol (e.g. Dante)? Interfaces using it get an IP address per inventory number.
        public bool $isIpBased = false,
    ) {
    }
}
