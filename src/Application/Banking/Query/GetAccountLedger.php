<?php

declare(strict_types=1);

namespace App\Application\Banking\Query;

use App\Application\Query\QueryInterface;

final class GetAccountLedger implements QueryInterface
{
    public function __construct(
        public readonly string $bankAccountId,
        public readonly AccountPeriod $period,
    ) {
    }
}
