<?php

declare(strict_types=1);

namespace App\Application\Supplier\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Supplier\SupplierRepositoryInterface;
use DateTimeImmutable;

final class UpdateSupplierHandler implements CommandHandlerInterface
{
    use ValidatesSupplierInput;

    public function __construct(private readonly SupplierRepositoryInterface $suppliers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateSupplier);

        $supplier = $this->suppliers->findById($command->id);
        if ($supplier === null) {
            throw new NotFoundException("Lieferant \"{$command->id}\" wurde nicht gefunden.");
        }

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

        $supplier->name = $data['name'];
        $supplier->type = $data['type'];
        $supplier->street = $data['street'];
        $supplier->postalCode = $data['postalCode'];
        $supplier->city = $data['city'];
        $supplier->phone = $data['phone'];
        $supplier->email = $data['email'];
        $supplier->customerNumber = $data['customerNumber'];
        $supplier->updatedAt = new DateTimeImmutable();

        $this->suppliers->save($supplier);

        return null;
    }
}
