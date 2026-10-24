<?php

declare(strict_types=1);

namespace App\Application\Customer\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class DeleteCustomerHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CustomerRepositoryInterface $customers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCustomer);

        $customer = $this->customers->findById($command->id);
        if ($customer === null) {
            throw new NotFoundException("Kunde \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->customers->delete($command->id);

        return null;
    }
}
