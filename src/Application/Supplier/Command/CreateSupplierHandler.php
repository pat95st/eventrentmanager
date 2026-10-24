<?php

declare(strict_types=1);

namespace App\Application\Supplier\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Supplier\Supplier;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateSupplierHandler implements CommandHandlerInterface
{
    use ValidatesSupplierInput;

    public function __construct(private readonly SupplierRepositoryInterface $suppliers)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateSupplier);

        [$errors, $data] = $this->validateSupplierInput(
            $command->name,
            $command->type,
            $command->street,
            $command->postalCode,
            $command->city,
            $command->phone,
            $command->email,
            $command->customerNumber,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $supplier = new Supplier(
            id: Uuid::v4(),
            name: $data['name'],
            type: $data['type'],
            street: $data['street'],
            postalCode: $data['postalCode'],
            city: $data['city'],
            phone: $data['phone'],
            email: $data['email'],
            customerNumber: $data['customerNumber'],
            createdAt: $now,
            updatedAt: $now,
        );

        $this->suppliers->save($supplier);

        return $supplier->id;
    }
}
