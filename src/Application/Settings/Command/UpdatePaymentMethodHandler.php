<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PaymentMethodRepositoryInterface;
use DateTimeImmutable;

/**
 * Bereits versendete Rechnungen behalten ihr Fälligkeitsdatum (beim
 * Versenden gesnapshotet) — eine spätere Änderung des Zahlungsziels wirkt
 * nur auf noch nicht versendete Dokumente.
 */
final class UpdatePaymentMethodHandler implements CommandHandlerInterface
{
    public function __construct(private readonly PaymentMethodRepositoryInterface $paymentMethods)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdatePaymentMethod);

        $method = $this->paymentMethods->findById($command->id);
        if ($method === null) {
            throw new NotFoundException("Zahlungsart \"{$command->id}\" wurde nicht gefunden.");
        }

        [$name, $days, $noteText, $errors] = ValidatesPaymentMethodInput::validate($command->name, $command->paymentTermDays, $command->noteText);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $method->name = $name;
        $method->paymentTermDays = $days;
        $method->noteText = $noteText;
        $method->updatedAt = new DateTimeImmutable();
        if ($command->isDefault) {
            $method->isDefault = true;
        }
        $this->paymentMethods->save($method);
        if ($command->isDefault) {
            $this->paymentMethods->markAsDefault($method->id);
        }

        return null;
    }
}
