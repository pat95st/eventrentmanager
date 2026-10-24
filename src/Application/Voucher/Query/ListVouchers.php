<?php

declare(strict_types=1);

namespace App\Application\Voucher\Query;

use App\Application\Query\QueryInterface;

final class ListVouchers implements QueryInterface
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $page = null,
        public readonly ?int $pageSize = null,
    ) {
    }
}
