<?php

declare(strict_types=1);

namespace App\Application\Banking\Command;

use App\Application\Command\CommandInterface;

/** Anfangsbestand eines Kontos setzen (Migration 131). */
final class SaveOpeningBalance implements CommandInterface
{
    public function __construct(
        public readonly string $bankAccountId,
        public readonly string $amount,      // Eingabe, z. B. "1.234,56" oder "-50"
        public readonly string $date = '',   // 'Y-m-d'; leer = gilt für alle Buchungen
    ) {
    }
}
