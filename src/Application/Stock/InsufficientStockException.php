<?php

declare(strict_types=1);

namespace App\Application\Stock;

use RuntimeException;

/** Verkaufsbestand reicht nicht aus, um die gewünschte Menge zu decken. */
final class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly int $availableQuantity, public readonly int $requestedQuantity)
    {
        parent::__construct("Im Verkaufsbestand sind nur {$availableQuantity} Stück verfügbar, angefragt wurden {$requestedQuantity}.");
    }
}
