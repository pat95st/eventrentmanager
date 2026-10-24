<?php

declare(strict_types=1);

namespace App\Application\Voucher\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Voucher\VoucherRepositoryInterface;
use DateTimeImmutable;

/**
 * Der Code selbst ist nach dem Anlegen NICHT mehr editierbar (analog zu
 * Inventurnummern) — nur Rabatt-Art/-Wert, Gültigkeitszeitraum und Notiz.
 */
final class UpdateVoucherHandler implements CommandHandlerInterface
{
    use ValidatesVoucherInput;

    public function __construct(private readonly VoucherRepositoryInterface $vouchers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateVoucher);

        $voucher = $this->vouchers->findById($command->id);
        if ($voucher === null) {
            throw new NotFoundException("Gutschein \"{$command->id}\" wurde nicht gefunden.");
        }

        [$errors, $data] = $this->validateVoucherInput(
            $command->discountType,
            $command->discountValue,
            $command->validFrom,
            $command->validUntil,
            $command->notes,
        );

        if ($data['validFrom'] === null && !isset($errors['validFrom'])) {
            $errors['validFrom'] = 'Bitte ein Gültig-ab-Datum angeben.';
        }
        if ($data['validUntil'] === null && !isset($errors['validUntil'])) {
            $errors['validUntil'] = 'Bitte ein Gültig-bis-Datum angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $voucher->discountType = $data['discountType'];
        $voucher->discountValue = $data['discountValue'];
        $voucher->validFrom = $data['validFrom'];
        $voucher->validUntil = $data['validUntil'];
        $voucher->notes = $data['notes'];
        $voucher->updatedAt = new DateTimeImmutable();

        $this->vouchers->save($voucher);

        return null;
    }
}
