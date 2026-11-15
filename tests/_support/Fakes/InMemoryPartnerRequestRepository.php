<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Federation\PartnerRequest;
use App\Domain\Federation\PartnerRequestDirection;
use App\Domain\Federation\PartnerRequestRepositoryInterface;
use App\Domain\Federation\PartnerRequestStatus;

final class InMemoryPartnerRequestRepository implements PartnerRequestRepositoryInterface
{
    /** @var array<string, PartnerRequest> */
    public array $requests = [];

    public function save(PartnerRequest $request): void
    {
        $this->requests[$request->id] = clone $request;
    }

    public function findById(string $id): ?PartnerRequest
    {
        return isset($this->requests[$id]) ? clone $this->requests[$id] : null;
    }

    public function findByReference(string $partnerId, PartnerRequestDirection $direction, string $reference): ?PartnerRequest
    {
        foreach ($this->requests as $request) {
            if ($request->partnerId === $partnerId && $request->direction === $direction && $request->reference === $reference) {
                return clone $request;
            }
        }

        return null;
    }

    public function findRecent(int $limit): array
    {
        return array_slice(array_reverse(array_values($this->requests)), 0, $limit);
    }

    public function findByRental(string $rentalId): array
    {
        return array_values(array_filter($this->requests, static fn ($r) => $r->rentalId === $rentalId));
    }

    public function countPendingIncoming(): int
    {
        return count(array_filter($this->requests, static fn ($r) => $r->isIncoming() && $r->status === PartnerRequestStatus::Pending));
    }

    public function only(): PartnerRequest
    {
        \PHPUnit\Framework\Assert::assertCount(1, $this->requests);

        return clone array_values($this->requests)[0];
    }
}
