<?php

declare(strict_types=1);

namespace App\Application\Voucher\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Voucher\VoucherRedemptionRepositoryInterface;

final class RemoveVoucherRedemptionHandler implements CommandHandlerInterface
{
    public function __construct(private readonly VoucherRedemptionRepositoryInterface $redemptions)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RemoveVoucherRedemption);

        $redemption = $this->redemptions->findByRentalId($command->rentalId);
        if ($redemption === null) {
            throw new NotFoundException('Dieser Vermietung ist aktuell kein Gutschein zugeordnet.');
        }

        $this->redemptions->deleteByVoucherId($redemption->voucherId);

        return null;
    }
}
