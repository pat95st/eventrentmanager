<?php

declare(strict_types=1);

namespace Tests\Unit\Application\RentalSeries;

use App\Application\RentalSeries\MaterialConflictFinder;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\FakePlannedMaterialReadModel;

final class MaterialConflictFinderTest extends Unit
{
    private function inventory(array $counts): InventoryNumberRepositoryInterface
    {
        return new class ($counts) implements InventoryNumberRepositoryInterface {
            public function __construct(private readonly array $counts)
            {
            }

            public function countActiveByArticles(array $articleIds): array
            {
                return array_intersect_key($this->counts, array_flip($articleIds));
            }

            public function save(InventoryNumber $inventoryNumber): void
            {
            }

            public function findById(string $id): ?InventoryNumber
            {
                return null;
            }

            public function findByNumber(string $number): ?InventoryNumber
            {
                return null;
            }

            public function findByArticle(string $articleId): array
            {
                return [];
            }

            public function findActiveByArticle(string $articleId): array
            {
                return [];
            }

            public function findByReceipt(string $receiptId): array
            {
                return [];
            }

            public function existsByNumber(string $number, ?string $excludeId = null): bool
            {
                return false;
            }

            public function findAllWithInstalledFirmwareVersion(): array
            {
                return [];
            }

            public function existsByInstalledFirmwareVersion(string $firmwareVersionId): bool
            {
                return false;
            }

            public function delete(string $id): void
            {
            }
        };
    }

    public function testMaximumOfASingleDayCounts(): void
    {
        $planned = new FakePlannedMaterialReadModel();
        // Two other rentals, overlapping only on 11.06.: 3 + 2 = 5 on that day.
        $planned->rows[] = ['rentalId' => 'a', 'articleId' => 'mh', 'quantity' => 3, 'from' => new DateTimeImmutable('2027-06-10'), 'to' => new DateTimeImmutable('2027-06-11')];
        $planned->rows[] = ['rentalId' => 'b', 'articleId' => 'mh', 'quantity' => 2, 'from' => new DateTimeImmutable('2027-06-11'), 'to' => new DateTimeImmutable('2027-06-12')];
        $finder = new MaterialConflictFinder($planned, $this->inventory(['mh' => 6, 'sub' => 4]));

        $conflicts = $finder->find(['mh' => 2, 'sub' => 4], new DateTimeImmutable('2027-06-10'), new DateTimeImmutable('2027-06-12'));

        $this->assertCount(1, $conflicts);
        $this->assertSame('mh', $conflicts[0]->articleId);
        $this->assertSame(5, $conflicts[0]->plannedElsewhere);
        $this->assertSame(1, $conflicts[0]->available());
    }

    public function testExcludedRentalsAndOtherPeriodsDoNotCount(): void
    {
        $planned = new FakePlannedMaterialReadModel();
        $planned->rows[] = ['rentalId' => 'self', 'articleId' => 'mh', 'quantity' => 6, 'from' => new DateTimeImmutable('2027-06-10'), 'to' => new DateTimeImmutable('2027-06-10')];
        $planned->rows[] = ['rentalId' => 'later', 'articleId' => 'mh', 'quantity' => 6, 'from' => new DateTimeImmutable('2027-07-01'), 'to' => new DateTimeImmutable('2027-07-01')];
        $finder = new MaterialConflictFinder($planned, $this->inventory(['mh' => 6]));

        $this->assertSame([], $finder->find(['mh' => 6], new DateTimeImmutable('2027-06-10'), new DateTimeImmutable('2027-06-10'), ['self']));
    }
}
