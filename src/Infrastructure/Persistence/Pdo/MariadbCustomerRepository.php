<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Customer\CustomerType;
use DateTimeImmutable;
use PDO;

final class MariadbCustomerRepository implements CustomerRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Customer $customer): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO customers (
                id, type, company_name, first_name, last_name, street, address_line2,
                postal_code, city, phone, email, telegram_chat_id, id_checked_at, created_at, updated_at
             ) VALUES (
                :id, :type, :company_name, :first_name, :last_name, :street, :address_line2,
                :postal_code, :city, :phone, :email, :telegram_chat_id, :id_checked_at, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                type = :type2, company_name = :company_name2, first_name = :first_name2,
                last_name = :last_name2, street = :street2, address_line2 = :address_line22,
                postal_code = :postal_code2, city = :city2, phone = :phone2, email = :email2,
                telegram_chat_id = :telegram_chat_id2, id_checked_at = :id_checked_at2, updated_at = :updated_at2'
        );

        $params = [
            'id' => $customer->id,
            'type' => $customer->type->value,
            'company_name' => $customer->companyName,
            'first_name' => $customer->firstName,
            'last_name' => $customer->lastName,
            'street' => $customer->street,
            'address_line2' => $customer->addressLine2,
            'postal_code' => $customer->postalCode,
            'city' => $customer->city,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'telegram_chat_id' => $customer->telegramChatId,
            'id_checked_at' => $customer->idCheckedAt?->format('Y-m-d H:i:s'),
            'created_at' => $customer->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $customer->updatedAt->format('Y-m-d H:i:s'),
        ];

        $stmt->execute([
            ...$params,
            'type2' => $customer->type->value,
            'company_name2' => $customer->companyName,
            'first_name2' => $customer->firstName,
            'last_name2' => $customer->lastName,
            'street2' => $customer->street,
            'address_line22' => $customer->addressLine2,
            'postal_code2' => $customer->postalCode,
            'city2' => $customer->city,
            'phone2' => $customer->phone,
            'email2' => $customer->email,
            'telegram_chat_id2' => $customer->telegramChatId,
            'id_checked_at2' => $customer->idCheckedAt?->format('Y-m-d H:i:s'),
            'updated_at2' => $customer->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Customer
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = $this->buildSearchWhere($search);

        $sql = 'SELECT * FROM customers' . $where . ' ORDER BY last_name ASC, company_name ASC, first_name ASC';

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

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM customers' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} [WHERE-Klausel (inkl. führendem Leerzeichen, ggf. leer), Parameter] */
    private function buildSearchWhere(?string $search): array
    {
        if ($search === null || trim($search) === '') {
            return ['', []];
        }

        $sql = ' WHERE company_name LIKE :search
                   OR first_name LIKE :search2
                   OR last_name LIKE :search3
                   OR city LIKE :search4
                   OR email LIKE :search5';
        $needle = '%' . $search . '%';
        $params = [
            'search' => $needle,
            'search2' => $needle,
            'search3' => $needle,
            'search4' => $needle,
            'search5' => $needle,
        ];

        return [$sql, $params];
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM customers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): Customer
    {
        return new Customer(
            id: $row['id'],
            type: CustomerType::from($row['type']),
            companyName: $row['company_name'],
            firstName: $row['first_name'],
            lastName: $row['last_name'],
            street: $row['street'],
            addressLine2: $row['address_line2'],
            postalCode: $row['postal_code'],
            city: $row['city'],
            phone: $row['phone'],
            email: $row['email'],
            telegramChatId: $row['telegram_chat_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            idCheckedAt: $row['id_checked_at'] !== null ? new DateTimeImmutable($row['id_checked_at']) : null,
        );
    }
}
