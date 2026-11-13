<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepositoryInterface;

/** In-Memory-Test-Double für CustomerRepositoryInterface (siehe tests/README.md). */
final class InMemoryCustomerRepository implements CustomerRepositoryInterface
{
    /** @var array<string, Customer> */
    private array $customers = [];

    public function save(Customer $customer): void
    {
        $this->customers[$customer->id] = $customer;
    }

    public function findById(string $id): ?Customer
    {
        return $this->customers[$id] ?? null;
    }

    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array
    {
        return array_values($this->customers);
    }

    public function countAll(?string $search = null): int
    {
        return count($this->customers);
    }

    public function delete(string $id): void
    {
        unset($this->customers[$id]);
    }
}
