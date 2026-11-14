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
    ) {
    }
}
