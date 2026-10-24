<?php

declare(strict_types=1);

namespace App\Application\Voucher\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Voucher\Voucher;
use App\Domain\Voucher\VoucherRepositoryInterface;

final class GetVoucherHandler implements QueryHandlerInterface
{
    public function __construct(private readonly VoucherRepositoryInterface $vouchers)
    {
    }

    public function handle(QueryInterface $query): Voucher
    {
        assert($query instanceof GetVoucher);

        $voucher = $this->vouchers->findById($query->id);
        if ($voucher === null) {
            throw new NotFoundException("Gutschein \"{$query->id}\" wurde nicht gefunden.");
        }

        return $voucher;
    }
}
