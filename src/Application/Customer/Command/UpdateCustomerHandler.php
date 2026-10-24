<?php

declare(strict_types=1);

namespace App\Application\Customer\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class UpdateCustomerHandler implements CommandHandlerInterface
{
    use ValidatesCustomerInput;

    public function __construct(private readonly CustomerRepositoryInterface $customers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateCustomer);

        $customer = $this->customers->findById($command->id);
        if ($customer === null) {
            throw new NotFoundException("Kunde \"{$command->id}\" wurde nicht gefunden.");
        }

        [$errors, $data] = $this->validateCustomerInput(
            $command->type,
            $command->companyName,
            $command->firstName,
            $command->lastName,
            $command->street,
            $command->addressLine2,
            $command->postalCode,
            $command->city,
            $command->phone,
            $command->email,
            $command->telegramChatId,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $customer->type = $data['type'];
        $customer->companyName = $data['companyName'];
        $customer->firstName = $data['firstName'];
        $customer->lastName = $data['lastName'];
        $customer->street = $data['street'];
        $customer->addressLine2 = $data['addressLine2'];
        $customer->postalCode = $data['postalCode'];
        $customer->city = $data['city'];
        $customer->phone = $data['phone'];
        $customer->email = $data['email'];
        $customer->telegramChatId = $data['telegramChatId'];
        $customer->updatedAt = new DateTimeImmutable();

        $this->customers->save($customer);

        return null;
    }
}
