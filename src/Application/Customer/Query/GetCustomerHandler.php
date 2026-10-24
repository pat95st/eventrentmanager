<?php

declare(strict_types=1);

namespace App\Application\Customer\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class GetCustomerHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CustomerRepositoryInterface $customers)
    {
    }

    public function handle(QueryInterface $query): Customer
    {
        assert($query instanceof GetCustomer);

        $customer = $this->customers->findById($query->id);
        if ($customer === null) {
            throw new NotFoundException("Kunde \"{$query->id}\" wurde nicht gefunden.");
        }

        return $customer;
    }
}
