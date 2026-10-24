<?php

declare(strict_types=1);

namespace App\Application\Rma\Command;

use App\Application\Command\CommandInterface;

final class CreateRma implements CommandInterface
{
    /** @param string[] $receiptIds */
    public function __construct(
        public readonly string $inventoryNumberId,
        public readonly ?string $reason,
        public readonly ?string $serviceProvider,
        public readonly ?string $rmaReference,
        public readonly ?string $sentAt,      // 'Y-m-d' oder null
        public readonly ?string $returnedAt,  // 'Y-m-d' oder null
        public readonly ?string $notes,
        public readonly string $kind,        // 'extern' | 'eigenreparatur'
        public readonly ?string $workedHours, // roher Formularwert, Komma oder Punkt, oder null
        public readonly array $receiptIds,    // string[] — nur bei kind === 'eigenreparatur' relevant
    ) {
    }
}
