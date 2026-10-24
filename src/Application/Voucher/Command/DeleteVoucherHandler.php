<?php

declare(strict_types=1);

namespace App\Application\Voucher\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Voucher\VoucherRedemptionRepositoryInterface;
use App\Domain\Voucher\VoucherRepositoryInterface;

final class DeleteVoucherHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly VoucherRepositoryInterface $vouchers,
        private readonly VoucherRedemptionRepositoryInterface $redemptions,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteVoucher);

        $voucher = $this->vouchers->findById($command->id);
        if ($voucher === null) {
            throw new NotFoundException("Gutschein \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($this->redemptions->findByVoucherId($command->id) !== null) {
            throw new ConflictException('Dieser Gutschein wurde bereits einer Vermietung zugeordnet. Bitte zuerst die Einlösung aufheben.');
        }

        $this->vouchers->delete($command->id);

        return null;
    }
}
