<?php

declare(strict_types=1);

namespace App\Application\Rma\Command;

use App\Application\Command\CommandInterface;

final class UpdateRma implements CommandInterface
{
    /** @param string[] $receiptIds */
    public function __construct(
        public readonly string $id,
        public readonly ?string $reason,
        public readonly ?string $serviceProvider,
        public readonly ?string $rmaReference,
        public readonly ?string $sentAt,
        public readonly ?string $returnedAt,
        public readonly ?string $notes,
        public readonly string $kind,        // 'extern' | 'eigenreparatur'
        public readonly ?string $workedHours, // roher Formularwert, Komma oder Punkt, oder null
        public readonly array $receiptIds,    // string[] — nur bei kind === 'eigenreparatur' relevant
    ) {
    }
}
