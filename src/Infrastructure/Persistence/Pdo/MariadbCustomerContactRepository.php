<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Customer\CustomerContact;
use App\Domain\Customer\CustomerContactRepositoryInterface;
use PDO;

final class MariadbCustomerContactRepository implements CustomerContactRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByCustomerId(string $customerId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customer_contacts WHERE customer_id = :customer_id ORDER BY sort_order ASC');
        $stmt->execute(['customer_id' => $customerId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findById(string $id): ?CustomerContact
    {
        $stmt = $this->pdo->prepare('SELECT * FROM customer_contacts WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function replaceForCustomer(string $customerId, array $contacts): void
    {
        $delete = $this->pdo->prepare('DELETE FROM customer_contacts WHERE customer_id = :customer_id');
        $delete->execute(['customer_id' => $customerId]);

        if ($contacts === []) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO customer_contacts (id, customer_id, name, job_title, phone, email, sort_order, street, address_line2, postal_code, city)
             VALUES (:id, :customer_id, :name, :job_title, :phone, :email, :sort_order, :street, :address_line2, :postal_code, :city)'
        );

        foreach ($contacts as $contact) {
            $insert->execute([
                'id' => $contact->id,
                'customer_id' => $customerId,
                'name' => $contact->name,
                'job_title' => $contact->jobTitle,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'sort_order' => $contact->sortOrder,
                'street' => $contact->street,
                'address_line2' => $contact->addressLine2,
                'postal_code' => $contact->postalCode,
                'city' => $contact->city,
            ]);
        }
    }

    private function hydrate(array $row): CustomerContact
    {
        return new CustomerContact(
            id: $row['id'],
            customerId: $row['customer_id'],
            name: $row['name'],
            jobTitle: $row['job_title'],
            phone: $row['phone'],
            email: $row['email'],
            sortOrder: (int) $row['sort_order'],
            street: $row['street'] ?? null,
            addressLine2: $row['address_line2'] ?? null,
            postalCode: $row['postal_code'] ?? null,
            city: $row['city'] ?? null,
        );
    }
}
