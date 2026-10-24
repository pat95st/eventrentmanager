<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use DateTimeImmutable;

final class ConfirmCustomerIdCheckedHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CustomerRepositoryInterface $customers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ConfirmCustomerIdChecked);

        $customer = $this->customers->findById($command->customerId);
        if ($customer === null) {
            throw new NotFoundException("Kunde \"{$command->customerId}\" wurde nicht gefunden.");
        }

        // Idempotent: ist bereits ein Zeitpunkt gesetzt, bleibt dieser
        // unverändert (einmalig je Kunde, siehe Customer::$idCheckedAt-Doc) —
        // ein erneutes Absenden der Checkbox setzt den Zeitpunkt nicht neu.
        if ($customer->idCheckedAt === null) {
            $customer->idCheckedAt = new DateTimeImmutable();
            $customer->updatedAt = new DateTimeImmutable();
            $this->customers->save($customer);
        }

        return null;
    }
}
