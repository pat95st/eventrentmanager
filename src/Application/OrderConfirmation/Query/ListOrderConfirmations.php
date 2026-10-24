<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Query;

use App\Application\Query\QueryInterface;
use App\Domain\OrderConfirmation\OrderConfirmationStatus;

final class ListOrderConfirmations implements QueryInterface
{
    public function __construct(
        public readonly ?string $searchTerm = null,
        public readonly ?OrderConfirmationStatus $status = null,
        public readonly int $page = 1,
        public readonly int $pageSize = 25,
    ) {
    }
}
