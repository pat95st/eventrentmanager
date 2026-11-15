<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Federation\PartnerInstance;
use App\Domain\Federation\PartnerInstanceRepositoryInterface;

final class InMemoryPartnerInstanceRepository implements PartnerInstanceRepositoryInterface
{
    /** @var array<string, PartnerInstance> */
    public array $partners = [];

    public function save(PartnerInstance $partner): void
    {
        $this->partners[$partner->id] = clone $partner;
    }

    public function findById(string $id): ?PartnerInstance
    {
        return isset($this->partners[$id]) ? clone $this->partners[$id] : null;
    }

    public function findByInboundTokenHash(string $hash): ?PartnerInstance
    {
        foreach ($this->partners as $partner) {
            if ($partner->inboundTokenHash === $hash) {
                return clone $partner;
            }
        }

        return null;
    }

    public function findAll(): array
    {
        return array_values(array_map(static fn ($p) => clone $p, $this->partners));
    }

    public function delete(string $id): void
    {
        unset($this->partners[$id]);
    }

    public function only(): PartnerInstance
    {
        \PHPUnit\Framework\Assert::assertCount(1, $this->partners);

        return clone array_values($this->partners)[0];
    }
}
