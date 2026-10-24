<?php

declare(strict_types=1);

namespace App\Application\Dunning\Query;

use App\Domain\Customer\Customer;
use App\Domain\Dunning\DunningNotice;
use App\Domain\Invoice\Invoice;

final class DunningNoticeDetail
{
    public function __construct(
        public readonly DunningNotice $notice,
        public readonly Invoice $invoice,
        public readonly ?Customer $customer,
    ) {
    }
}
