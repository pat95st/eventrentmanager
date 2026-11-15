<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Network\ArticleNetworkSettings;
use App\Domain\Network\Network;
use App\Domain\Network\NetworkAddressEntry;
use App\Domain\Network\NetworkCategoryRange;
use App\Domain\Network\NetworkInterfaceAssignment;
use App\Domain\Network\NetworkRepositoryInterface;

/** In-memory networks; the "number" of an inventory number is its ID here. */
final class InMemoryNetworkRepository implements NetworkRepositoryInterface
{
    /** @var array<string, Network> */
    public array $networks = [];

    /** @var array<string, NetworkCategoryRange> */
    public array $ranges = [];

    /** @var array<string, ArticleNetworkSettings> */
    public array $articleSettings = [];

    /** @var array<string, NetworkInterfaceAssignment[]> */
    public array $assignments = [];

    public function findAll(): array
    {
        $networks = array_values($this->networks);
        usort($networks, static fn (Network $a, Network $b) => [$a->sortOrder, $a->name] <=> [$b->sortOrder, $b->name]);

        return $networks;
    }

    public function findById(string $id): ?Network
    {
        return $this->networks[$id] ?? null;
    }

    public function save(Network $network): void
    {
        $this->networks[$network->id] = $network;
    }

    public function delete(string $id): void
    {
        unset($this->networks[$id]);
        $this->ranges = array_filter($this->ranges, static fn (NetworkCategoryRange $r) => $r->networkId !== $id);
    }

    public function nameExists(string $name, ?string $excludeId = null): bool
    {
        foreach ($this->networks as $network) {
            if ($network->id !== $excludeId && mb_strtolower($network->name) === mb_strtolower($name)) {
                return true;
            }
        }

        return false;
    }

    public function assignmentCounts(): array
    {
        $counts = [];
        foreach ($this->allAssignments() as $assignment) {
            if ($assignment->networkId !== null) {
                $counts[$assignment->networkId] = ($counts[$assignment->networkId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function findAllCategoryRanges(): array
    {
        return array_values($this->ranges);
    }

    public function findCategoryRange(string $categoryId): ?NetworkCategoryRange
    {
        return $this->ranges[$categoryId] ?? null;
    }

    public function saveCategoryRange(NetworkCategoryRange $range): void
    {
        $this->ranges[$range->categoryId] = $range;
    }

    public function deleteCategoryRange(string $categoryId): void
    {
        unset($this->ranges[$categoryId]);
    }

    public function findArticleSettings(string $articleId): ?ArticleNetworkSettings
    {
        return $this->articleSettings[$articleId] ?? null;
    }

    public function saveArticleSettings(ArticleNetworkSettings $settings): void
    {
        $this->articleSettings[$settings->articleId] = $settings;
    }

    public function deleteArticleSettings(string $articleId): void
    {
        unset($this->articleSettings[$articleId]);
    }

    public function findAssignmentsByInventoryNumber(string $inventoryNumberId): array
    {
        return $this->assignments[$inventoryNumberId] ?? [];
    }

    public function findAssignmentsByInventoryNumbers(array $inventoryNumberIds): array
    {
        return array_intersect_key($this->assignments, array_flip($inventoryNumberIds));
    }

    public function replaceAssignments(string $inventoryNumberId, array $assignments): void
    {
        $this->assignments[$inventoryNumberId] = array_values($assignments);
    }

    public function usedAddresses(string $networkId, int $version, ?string $excludeInventoryNumberId = null): array
    {
        $used = [];
        foreach ($this->allAssignments() as $assignment) {
            if ($assignment->networkId === $networkId && $assignment->inventoryNumberId !== $excludeInventoryNumberId && $assignment->address($version) !== null) {
                $used[] = (string) $assignment->address($version);
            }
        }

        return $used;
    }

    public function findAddressOwner(string $networkId, int $version, string $address, ?string $excludeInventoryNumberId = null): ?string
    {
        foreach ($this->allAssignments() as $assignment) {
            if ($assignment->networkId === $networkId && $assignment->address($version) === $address && $assignment->inventoryNumberId !== $excludeInventoryNumberId) {
                return $assignment->inventoryNumberId;
            }
        }

        return null;
    }

    public function findMacOwner(string $macAddress, ?string $excludeInventoryNumberId = null): ?string
    {
        foreach ($this->allAssignments() as $assignment) {
            if ($assignment->macAddress === $macAddress && $assignment->inventoryNumberId !== $excludeInventoryNumberId) {
                return $assignment->inventoryNumberId;
            }
        }

        return null;
    }

    public function findAddressList(string $networkId): array
    {
        $entries = [];
        foreach ($this->allAssignments() as $assignment) {
            if ($assignment->networkId === $networkId) {
                $entries[] = new NetworkAddressEntry($assignment, $assignment->inventoryNumberId, 'article', 'Artikel');
            }
        }

        return $entries;
    }

    /** @return NetworkInterfaceAssignment[] */
    private function allAssignments(): array
    {
        return array_merge([], ...array_values($this->assignments));
    }
}
