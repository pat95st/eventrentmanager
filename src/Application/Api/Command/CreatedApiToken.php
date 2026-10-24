<?php

declare(strict_types=1);

namespace App\Application\Api\Command;

/** Rückgabe von CreateApiTokenHandler — enthält den Klartext-Token, der nur dieses eine Mal verfügbar ist. */
final class CreatedApiToken
{
    public function __construct(
        public readonly string $id,
        public readonly string $rawToken,
    ) {
    }
}
