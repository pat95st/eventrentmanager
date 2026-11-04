<?php

declare(strict_types=1);

namespace App\Application\Banking\Command;

use App\Application\Command\CommandInterface;

/** Beleg vom Konto abbuchen; der Beleg wird als bezahlt markiert (Migration 131). */
final class BookReceiptPayment implements CommandInterface
{
    public function __construct(
        public readonly string $bankAccountId,
        public readonly string $receiptId,
        public readonly string $bookingDate, // 'Y-m-d'
    ) {
    }
}
