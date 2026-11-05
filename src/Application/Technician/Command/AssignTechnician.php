<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandInterface;

/** Techniker zu einer Veranstaltung anfragen bzw. direkt einplanen und benachrichtigen. */
final class AssignTechnician implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $userId,
        /** @var string[] */
        public readonly array $tradeIds,
        public readonly ?string $note = null,
    ) {
    }
}
