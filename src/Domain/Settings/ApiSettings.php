<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/** Globaler Ein-/Ausschalter für die REST-API (siehe ApiTokenGuard) — ist er aus, antworten alle /api/v1/*-Routen mit 404, unabhängig von gültigen Tokens. */
final class ApiSettings
{
    public function __construct(
        public bool $enabled = false,
    ) {
    }
}
