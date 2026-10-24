<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Supplier\Supplier;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Domain\Supplier\SupplierType;
use DateTimeImmutable;
use PDO;

final class MariadbSupplierRepository implements SupplierRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Supplier $supplier): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO suppliers (
                id, name, type, street, postal_code, city, phone, email, customer_number, created_at, updated_at
             ) VALUES (
                :id, :name, :type, :street, :postal_code, :city, :phone, :email, :customer_number, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                name = :name2, type = :type2, street = :street2, postal_code = :postal_code2,
                city = :city2, phone = :phone2, email = :email2, customer_number = :customer_number2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $supplier->id,
            'name' => $supplier->name,
            'type' => $supplier->type->value,
            'street' => $supplier->street,
            'postal_code' => $supplier->postalCode,
            'city' => $supplier->city,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'customer_number' => $supplier->customerNumber,
            'created_at' => $supplier->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $supplier->updatedAt->format('Y-m-d H:i:s'),
            'name2' => $supplier->name,
            'type2' => $supplier->type->value,
            'street2' => $supplier->street,
            'postal_code2' => $supplier->postalCode,
            'city2' => $supplier->city,
            'phone2' => $supplier->phone,
            'email2' => $supplier->email,
            'customer_number2' => $supplier->customerNumber,
            'updated_at2' => $supplier->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Supplier
    {
        $stmt = $this->pdo->prepare('SELECT * FROM suppliers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = $this->buildSearchWhere($search);

        $sql = 'SELECT * FROM suppliers' . $where . ' ORDER BY name ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit !== null) {
            $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
            $stmt->bindValue('offset', max(0, $offset ?? 0), PDO::PARAM_INT);
        }
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countAll(?string $search = null): int
    {
        [$where, $params] = $this->buildSearchWhere($search);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM suppliers' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function buildSearchWhere(?string $search): array
    {
        if ($search === null || trim($search) === '') {
            return ['', []];
        }

        $needle = '%' . $search . '%';

        return [' WHERE name LIKE :search OR city LIKE :search2 OR email LIKE :search3', ['search' => $needle, 'search2' => $needle, 'search3' => $needle]];
    }

    public function isUsedByInventoryNumbers(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM inventory_numbers WHERE supplier_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function isUsedByReceipts(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM receipts WHERE supplier_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM suppliers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): Supplier
    {
        return new Supplier(
            id: $row['id'],
            name: $row['name'],
            type: SupplierType::from($row['type']),
            street: $row['street'],
            postalCode: $row['postal_code'],
            city: $row['city'],
            phone: $row['phone'],
            email: $row['email'],
            customerNumber: $row['customer_number'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
