<?php

declare(strict_types=1);

namespace App\Domain\Network;

/** Networks, category ranges, article network settings and address assignments (migration 155). */
interface NetworkRepositoryInterface
{
    /** @return Network[] sorted */
    public function findAll(): array;

    public function findById(string $id): ?Network;

    public function save(Network $network): void;

    public function delete(string $id): void;

    public function nameExists(string $name, ?string $excludeId = null): bool;

    /** @return array<string, int> network ID => number of assigned interfaces */
    public function assignmentCounts(): array;

    /** @return NetworkCategoryRange[] */
    public function findAllCategoryRanges(): array;

    public function findCategoryRange(string $categoryId): ?NetworkCategoryRange;

    public function saveCategoryRange(NetworkCategoryRange $range): void;

    public function deleteCategoryRange(string $categoryId): void;

    public function findArticleSettings(string $articleId): ?ArticleNetworkSettings;

    public function saveArticleSettings(ArticleNetworkSettings $settings): void;

    public function deleteArticleSettings(string $articleId): void;

    /** @return NetworkInterfaceAssignment[] */
    public function findAssignmentsByInventoryNumber(string $inventoryNumberId): array;

    /**
     * @param string[] $inventoryNumberIds
     * @return array<string, NetworkInterfaceAssignment[]> inventory number ID => assignments
     */
    public function findAssignmentsByInventoryNumbers(array $inventoryNumberIds): array;

    /**
     * Replaces all assignments of the inventory number.
     *
     * @param NetworkInterfaceAssignment[] $assignments
     */
    public function replaceAssignments(string $inventoryNumberId, array $assignments): void;

    /**
     * Assigned addresses (canonical text) of the network, except those of the given inventory number.
     *
     * @return string[]
     */
    public function usedAddresses(string $networkId, int $version, ?string $excludeInventoryNumberId = null): array;

    /** Inventory number (its "number") already using the address in the network, except the given one. */
    public function findAddressOwner(string $networkId, int $version, string $address, ?string $excludeInventoryNumberId = null): ?string;

    /** Inventory number (its "number") already using the MAC address, except the given one. */
    public function findMacOwner(string $macAddress, ?string $excludeInventoryNumberId = null): ?string;

    /** @return NetworkAddressEntry[] all assignments of the network */
    public function findAddressList(string $networkId): array;
}
