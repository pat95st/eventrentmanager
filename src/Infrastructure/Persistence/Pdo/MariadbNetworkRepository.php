<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Network\ArticleNetworkSettings;
use App\Domain\Network\IpMode;
use App\Domain\Network\Network;
use App\Domain\Network\NetworkAddressEntry;
use App\Domain\Network\NetworkCategoryRange;
use App\Domain\Network\NetworkInterfaceAssignment;
use App\Domain\Network\NetworkRepositoryInterface;
use PDO;

/** Networks and address assignments (migration 155). */
final class MariadbNetworkRepository implements NetworkRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(): array
    {
        return array_map($this->hydrateNetwork(...), $this->pdo->query('SELECT * FROM networks ORDER BY sort_order, name')->fetchAll());
    }

    public function findById(string $id): ?Network
    {
        $stmt = $this->pdo->prepare('SELECT * FROM networks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateNetwork($row) : null;
    }

    public function save(Network $network): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO networks (id, name, ip_mode, vlan_id, ipv4_network, ipv4_prefix, ipv6_network, ipv6_prefix, ipv6_default_prefix, notes, sort_order)
             VALUES (:id, :name, :ip_mode, :vlan_id, :ipv4_network, :ipv4_prefix, :ipv6_network, :ipv6_prefix, :ipv6_default_prefix, :notes, :sort_order)
             ON DUPLICATE KEY UPDATE name = VALUES(name), ip_mode = VALUES(ip_mode), vlan_id = VALUES(vlan_id),
                ipv4_network = VALUES(ipv4_network), ipv4_prefix = VALUES(ipv4_prefix), ipv6_network = VALUES(ipv6_network),
                ipv6_prefix = VALUES(ipv6_prefix), ipv6_default_prefix = VALUES(ipv6_default_prefix), notes = VALUES(notes),
                sort_order = VALUES(sort_order)'
        );
        $stmt->execute([
            'id' => $network->id,
            'name' => $network->name,
            'ip_mode' => $network->mode->value,
            'vlan_id' => $network->vlanId,
            'ipv4_network' => $network->ipv4Network,
            'ipv4_prefix' => $network->ipv4Prefix,
            'ipv6_network' => $network->ipv6Network,
            'ipv6_prefix' => $network->ipv6Prefix,
            'ipv6_default_prefix' => $network->ipv6DefaultPrefix,
            'notes' => $network->notes,
            'sort_order' => $network->sortOrder,
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM networks WHERE id = :id')->execute(['id' => $id]);
    }

    public function nameExists(string $name, ?string $excludeId = null): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM networks WHERE name = :name AND id <> :exclude');
        $stmt->execute(['name' => $name, 'exclude' => $excludeId ?? '']);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function assignmentCounts(): array
    {
        $counts = [];
        $rows = $this->pdo->query(
            'SELECT network_id, COUNT(*) AS cnt FROM inventory_number_network_interfaces WHERE network_id IS NOT NULL GROUP BY network_id'
        )->fetchAll();
        foreach ($rows as $row) {
            $counts[(string) $row['network_id']] = (int) $row['cnt'];
        }

        return $counts;
    }

    public function findAllCategoryRanges(): array
    {
        return array_map($this->hydrateRange(...), $this->pdo->query('SELECT * FROM network_category_ranges')->fetchAll());
    }

    public function findCategoryRange(string $categoryId): ?NetworkCategoryRange
    {
        $stmt = $this->pdo->prepare('SELECT * FROM network_category_ranges WHERE category_id = :id');
        $stmt->execute(['id' => $categoryId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateRange($row) : null;
    }

    public function saveCategoryRange(NetworkCategoryRange $range): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO network_category_ranges (category_id, network_id, ipv4_start, ipv4_end, ipv6_start, ipv6_end)
             VALUES (:category_id, :network_id, :ipv4_start, :ipv4_end, :ipv6_start, :ipv6_end)
             ON DUPLICATE KEY UPDATE network_id = VALUES(network_id), ipv4_start = VALUES(ipv4_start), ipv4_end = VALUES(ipv4_end),
                ipv6_start = VALUES(ipv6_start), ipv6_end = VALUES(ipv6_end)'
        );
        $stmt->execute([
            'category_id' => $range->categoryId,
            'network_id' => $range->networkId,
            'ipv4_start' => $range->ipv4Start,
            'ipv4_end' => $range->ipv4End,
            'ipv6_start' => $range->ipv6Start,
            'ipv6_end' => $range->ipv6End,
        ]);
    }

    public function deleteCategoryRange(string $categoryId): void
    {
        $this->pdo->prepare('DELETE FROM network_category_ranges WHERE category_id = :id')->execute(['id' => $categoryId]);
    }

    public function findArticleSettings(string $articleId): ?ArticleNetworkSettings
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_network_settings WHERE article_id = :id');
        $stmt->execute(['id' => $articleId]);
        $row = $stmt->fetch();

        return $row ? new ArticleNetworkSettings($row['article_id'], (bool) $row['has_network_interface'], (bool) $row['requires_dhcp']) : null;
    }

    public function saveArticleSettings(ArticleNetworkSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_network_settings (article_id, has_network_interface, requires_dhcp)
             VALUES (:article_id, :has_network_interface, :requires_dhcp)
             ON DUPLICATE KEY UPDATE has_network_interface = VALUES(has_network_interface), requires_dhcp = VALUES(requires_dhcp)'
        );
        $stmt->execute([
            'article_id' => $settings->articleId,
            'has_network_interface' => $settings->hasNetworkInterface ? 1 : 0,
            'requires_dhcp' => $settings->requiresDhcp ? 1 : 0,
        ]);
    }

    public function deleteArticleSettings(string $articleId): void
    {
        $this->pdo->prepare('DELETE FROM article_network_settings WHERE article_id = :id')->execute(['id' => $articleId]);
    }

    public function findAssignmentsByInventoryNumber(string $inventoryNumberId): array
    {
        return $this->findAssignmentsByInventoryNumbers([$inventoryNumberId])[$inventoryNumberId] ?? [];
    }

    public function findAssignmentsByInventoryNumbers(array $inventoryNumberIds): array
    {
        $inventoryNumberIds = array_values(array_unique($inventoryNumberIds));
        if ($inventoryNumberIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($inventoryNumberIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM inventory_number_network_interfaces WHERE inventory_number_id IN ({$placeholders}) ORDER BY inventory_number_id, interface_key"
        );
        $stmt->execute($inventoryNumberIds);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(string) $row['inventory_number_id']][] = $this->hydrateAssignment($row);
        }

        return $result;
    }

    public function replaceAssignments(string $inventoryNumberId, array $assignments): void
    {
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $this->pdo->prepare('DELETE FROM inventory_number_network_interfaces WHERE inventory_number_id = :id')
                ->execute(['id' => $inventoryNumberId]);
            $insert = $this->pdo->prepare(
                'INSERT INTO inventory_number_network_interfaces
                    (inventory_number_id, interface_key, label, network_id, ipv4_address, ipv6_address, mac_address)
                 VALUES (:inventory_number_id, :interface_key, :label, :network_id, :ipv4_address, :ipv6_address, :mac_address)'
            );
            foreach ($assignments as $assignment) {
                $insert->execute([
                    'inventory_number_id' => $inventoryNumberId,
                    'interface_key' => $assignment->interfaceKey,
                    'label' => mb_substr($assignment->label, 0, 150),
                    'network_id' => $assignment->networkId,
                    'ipv4_address' => $assignment->ipv4Address,
                    'ipv6_address' => $assignment->ipv6Address,
                    'mac_address' => $assignment->macAddress,
                ]);
            }
            if ($ownTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function usedAddresses(string $networkId, int $version, ?string $excludeInventoryNumberId = null): array
    {
        $column = $version === 4 ? 'ipv4_address' : 'ipv6_address';
        $stmt = $this->pdo->prepare(
            "SELECT {$column} FROM inventory_number_network_interfaces
             WHERE network_id = :network_id AND {$column} IS NOT NULL AND inventory_number_id <> :exclude"
        );
        $stmt->execute(['network_id' => $networkId, 'exclude' => $excludeInventoryNumberId ?? '']);

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function findAddressOwner(string $networkId, int $version, string $address, ?string $excludeInventoryNumberId = null): ?string
    {
        $column = $version === 4 ? 'ipv4_address' : 'ipv6_address';
        $stmt = $this->pdo->prepare(
            "SELECT i.number FROM inventory_number_network_interfaces n
             JOIN inventory_numbers i ON i.id = n.inventory_number_id
             WHERE n.network_id = :network_id AND n.{$column} = :address AND n.inventory_number_id <> :exclude
             LIMIT 1"
        );
        $stmt->execute(['network_id' => $networkId, 'address' => $address, 'exclude' => $excludeInventoryNumberId ?? '']);
        $number = $stmt->fetchColumn();

        return $number === false ? null : (string) $number;
    }

    public function findMacOwner(string $macAddress, ?string $excludeInventoryNumberId = null): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.number FROM inventory_number_network_interfaces n
             JOIN inventory_numbers i ON i.id = n.inventory_number_id
             WHERE n.mac_address = :mac AND n.inventory_number_id <> :exclude
             LIMIT 1'
        );
        $stmt->execute(['mac' => $macAddress, 'exclude' => $excludeInventoryNumberId ?? '']);
        $number = $stmt->fetchColumn();

        return $number === false ? null : (string) $number;
    }

    public function findAddressList(string $networkId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.*, i.number AS inventory_number, a.id AS article_id,
                    TRIM(CONCAT(COALESCE(m.name, \'\'), \' \', a.name)) AS article_name
             FROM inventory_number_network_interfaces n
             JOIN inventory_numbers i ON i.id = n.inventory_number_id
             JOIN articles a ON a.id = i.article_id
             LEFT JOIN manufacturers m ON m.id = a.manufacturer_id
             WHERE n.network_id = :network_id'
        );
        $stmt->execute(['network_id' => $networkId]);

        return array_map(fn (array $row) => new NetworkAddressEntry(
            $this->hydrateAssignment($row),
            (string) $row['inventory_number'],
            (string) $row['article_id'],
            (string) $row['article_name'],
        ), $stmt->fetchAll());
    }

    /** @param array<string, mixed> $row */
    private function hydrateNetwork(array $row): Network
    {
        $int = static fn (mixed $value) => $value === null ? null : (int) $value;

        return new Network(
            (string) $row['id'],
            (string) $row['name'],
            IpMode::tryFrom((string) $row['ip_mode']) ?? IpMode::Ipv4,
            $int($row['vlan_id']),
            $row['ipv4_network'] !== null ? (string) $row['ipv4_network'] : null,
            $int($row['ipv4_prefix']),
            $row['ipv6_network'] !== null ? (string) $row['ipv6_network'] : null,
            $int($row['ipv6_prefix']),
            $int($row['ipv6_default_prefix']),
            $row['notes'] !== null ? (string) $row['notes'] : null,
            (int) $row['sort_order'],
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateRange(array $row): NetworkCategoryRange
    {
        return new NetworkCategoryRange(
            (string) $row['category_id'],
            (string) $row['network_id'],
            $row['ipv4_start'] !== null ? (string) $row['ipv4_start'] : null,
            $row['ipv4_end'] !== null ? (string) $row['ipv4_end'] : null,
            $row['ipv6_start'] !== null ? (string) $row['ipv6_start'] : null,
            $row['ipv6_end'] !== null ? (string) $row['ipv6_end'] : null,
        );
    }

    /** @param array<string, mixed> $row */
    private function hydrateAssignment(array $row): NetworkInterfaceAssignment
    {
        return new NetworkInterfaceAssignment(
            (string) $row['inventory_number_id'],
            (string) $row['interface_key'],
            (string) $row['label'],
            $row['network_id'] !== null ? (string) $row['network_id'] : null,
            $row['ipv4_address'] !== null ? (string) $row['ipv4_address'] : null,
            $row['ipv6_address'] !== null ? (string) $row['ipv6_address'] : null,
            $row['mac_address'] !== null ? (string) $row['mac_address'] : null,
        );
    }
}
