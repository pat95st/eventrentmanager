<?php

declare(strict_types=1);

namespace App\Application\Voucher\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\VoucherSettingsRepositoryInterface;
use App\Domain\Voucher\Voucher;
use App\Domain\Voucher\VoucherCodeGenerator;
use App\Domain\Voucher\VoucherRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateVoucherHandler implements CommandHandlerInterface
{
    use ValidatesVoucherInput;

    /** Versuche, einen noch nicht vergebenen Zufallscode zu finden, bevor aufgegeben wird (siehe handle()). */
    private const MAX_CODE_ATTEMPTS = 20;

    public function __construct(
        private readonly VoucherRepositoryInterface $vouchers,
        private readonly VoucherSettingsRepositoryInterface $voucherSettings,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateVoucher);

        [$errors, $data] = $this->validateVoucherInput(
            $command->discountType,
            $command->discountValue,
            $command->validFrom,
            $command->validUntil,
            $command->notes,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $validFrom = $data['validFrom'] ?? $now;
        $validUntil = $data['validUntil'] ?? $validFrom->modify('+' . $this->voucherSettings->get()->defaultValidityDays . ' days');

        $prefix = $command->codePrefix !== null ? trim($command->codePrefix) : '';

        $code = null;
        for ($attempt = 0; $attempt < self::MAX_CODE_ATTEMPTS; $attempt++) {
            $candidate = VoucherCodeGenerator::generate($prefix !== '' ? $prefix : null);
            if ($this->vouchers->findByCode($candidate) === null) {
                $code = $candidate;
                break;
            }
        }

        if ($code === null) {
            // Bei ~33^10 möglichen Zufallscodes praktisch ausgeschlossen,
            // aber ein klarer Fehler ist hier besser als eine Endlosschleife.
            throw new ValidationException(['code' => 'Es konnte kein eindeutiger Gutscheincode erzeugt werden. Bitte erneut versuchen.']);
        }

        $voucher = new Voucher(
            id: Uuid::v4(),
            code: $code,
            discountType: $data['discountType'],
            discountValue: $data['discountValue'],
            validFrom: $validFrom,
            validUntil: $validUntil,
            notes: $data['notes'],
            createdAt: $now,
            updatedAt: $now,
        );

        $this->vouchers->save($voucher);

        return $voucher->id;
    }
}
