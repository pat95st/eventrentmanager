<?php

declare(strict_types=1);

namespace App\Application\Voucher\Query;

use App\Application\Query\QueryInterface;

final class GetVoucher implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
