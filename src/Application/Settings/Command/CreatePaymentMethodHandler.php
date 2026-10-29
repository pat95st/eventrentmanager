<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PaymentMethod;
use App\Domain\Settings\PaymentMethodRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreatePaymentMethodHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PaymentMethodRepositoryInterface $paymentMethods)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreatePaymentMethod);

        [$name, $days, $noteText, $errors] = ValidatesPaymentMethodInput::validate($command->name, $command->paymentTermDays, $command->noteText);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $isDefault = $command->isDefault || $this->paymentMethods->findDefault() === null;

        $now = new DateTimeImmutable();
        $method = new PaymentMethod(
            id: Uuid::v4(),
            name: $name,
            paymentTermDays: $days,
            noteText: $noteText,
            isDefault: $isDefault,
            createdAt: $now,
            updatedAt: $now,
        );
        $this->paymentMethods->save($method);
        if ($isDefault) {
            $this->paymentMethods->markAsDefault($method->id);
        }

        return $method->id;
    }
}
