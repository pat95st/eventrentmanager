<?php

declare(strict_types=1);

namespace App\Application\Voucher\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Voucher\VoucherRedemption;
use App\Domain\Voucher\VoucherRedemptionRepositoryInterface;
use App\Domain\Voucher\VoucherRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Ordnet einen per Code eingegebenen Gutschein einer Vermietung zu
 * ("einlösen"). Ein Gutschein kann höchstens einmal eingelöst werden, eine
 * Vermietung trägt höchstens einen Gutschein gleichzeitig (siehe
 * voucher_redemptions, UNIQUE auf beiden Spalten) — eine erneute Zuordnung
 * ist erst nach "Einlösung aufheben" (RemoveVoucherRedemption) wieder
 * möglich, analog zur Fahrzeug-Beladungszuordnung (RentalVehicleLoad).
 */
final class RedeemVoucherForRentalHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly VoucherRepositoryInterface $vouchers,
        private readonly VoucherRedemptionRepositoryInterface $redemptions,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RedeemVoucherForRental);

        $code = trim($command->code);
        if ($code === '') {
            throw new ValidationException(['code' => 'Bitte einen Gutscheincode angeben.']);
        }

        $voucher = $this->vouchers->findByCode($code);
        if ($voucher === null) {
            throw new ValidationException(['code' => "Gutscheincode \"{$code}\" wurde nicht gefunden."]);
        }

        if ($this->redemptions->findByVoucherId($voucher->id) !== null) {
            throw new ValidationException(['code' => 'Dieser Gutschein wurde bereits eingelöst.']);
        }

        if ($this->redemptions->findByRentalId($command->rentalId) !== null) {
            throw new ValidationException(['code' => 'Dieser Vermietung ist bereits ein Gutschein zugeordnet.']);
        }

        $now = new DateTimeImmutable();
        if (!$voucher->isValidNow($now)) {
            throw new ValidationException(['code' => 'Dieser Gutschein ist aktuell nicht gültig (abgelaufen oder noch nicht gültig).']);
        }

        $this->redemptions->save(new VoucherRedemption(
            id: Uuid::v4(),
            voucherId: $voucher->id,
            rentalId: $command->rentalId,
            redeemedAt: $now,
        ));

        return null;
    }
}
