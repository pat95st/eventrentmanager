<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

final class EmailPackingListPdf implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $recipientEmail,
        public readonly ?string $note, // optionale kurze Notiz, wird in den Mailtext übernommen
    ) {
    }
}
