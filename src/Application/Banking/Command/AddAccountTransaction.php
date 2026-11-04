<?php

declare(strict_types=1);

namespace App\Application\Banking\Command;

use App\Application\Command\CommandInterface;

/** Zahlungseingang/-ausgang ohne Beleg, z. B. Kontogebühren (Migration 131). */
final class AddAccountTransaction implements CommandInterface
{
    public function __construct(
        public readonly string $bankAccountId,
        public readonly string $bookingDate,  // 'Y-m-d'
        public readonly string $direction,    // 'in' | 'out'
        public readonly string $amount,       // positiver Betrag
        public readonly string $description,
        public readonly string $counterparty = '',
    ) {
    }
}
