<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\User\Permission;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbUserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(User $user): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users (
                id, email, first_name, last_name, password_hash, permissions, two_factor_method, totp_secret,
                must_change_password, must_setup_two_factor, is_active, default_page_size, locale, notify_on_new_receipt_email, created_at, updated_at
             ) VALUES (
                :id, :email, :first_name, :last_name, :password_hash, :permissions, :two_factor_method, :totp_secret,
                :must_change_password, :must_setup_two_factor, :is_active, :default_page_size, :locale, :notify_on_new_receipt_email, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                email = :email2, first_name = :first_name2, last_name = :last_name2, password_hash = :password_hash2, permissions = :permissions2,
                two_factor_method = :two_factor_method2, totp_secret = :totp_secret2,
                must_change_password = :must_change_password2, must_setup_two_factor = :must_setup_two_factor2, is_active = :is_active2,
                default_page_size = :default_page_size2, locale = :locale2, notify_on_new_receipt_email = :notify_on_new_receipt_email2, updated_at = :updated_at2'
        );

        $permissions = implode(',', array_map(static fn (Permission $p) => $p->value, $user->permissions));

        $params = [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->firstName,
            'last_name' => $user->lastName,
            'password_hash' => $user->passwordHash,
            'permissions' => $permissions,
            'two_factor_method' => $user->twoFactorMethod->value,
            'totp_secret' => $user->totpSecret,
            'must_change_password' => $user->mustChangePassword ? 1 : 0,
            'must_setup_two_factor' => $user->mustSetupTwoFactor ? 1 : 0,
            'is_active' => $user->isActive ? 1 : 0,
            'default_page_size' => $user->defaultPageSize,
            'locale' => $user->locale,
            'notify_on_new_receipt_email' => $user->notifyOnNewReceiptEmail ? 1 : 0,
            'created_at' => $user->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $user->updatedAt->format('Y-m-d H:i:s'),
        ];

        $stmt->execute([
            ...$params,
            'email2' => $user->email,
            'first_name2' => $user->firstName,
            'last_name2' => $user->lastName,
            'password_hash2' => $user->passwordHash,
            'permissions2' => $permissions,
            'two_factor_method2' => $user->twoFactorMethod->value,
            'totp_secret2' => $user->totpSecret,
            'must_change_password2' => $user->mustChangePassword ? 1 : 0,
            'must_setup_two_factor2' => $user->mustSetupTwoFactor ? 1 : 0,
            'is_active2' => $user->isActive ? 1 : 0,
            'default_page_size2' => $user->defaultPageSize,
            'locale2' => $user->locale,
            'notify_on_new_receipt_email2' => $user->notifyOnNewReceiptEmail ? 1 : 0,
            'updated_at2' => $user->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function existsByEmail(string $email, ?string $excludingId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email';
        $params = ['email' => $email];

        if ($excludingId !== null) {
            $sql .= ' AND id != :excluding_id';
            $params['excluding_id'] = $excludingId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function findAll(int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users ORDER BY email ASC LIMIT :limit OFFSET :offset');
        $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countAll(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM users');

        return (int) $stmt->fetchColumn();
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function countActiveAdministrators(?string $excludingId = null): int
    {
        // "administration" kann irgendwo in der kommaseparierten Liste
        // stehen, daher FIND_IN_SET statt einem einfachen Gleichheits-/
        // LIKE-Vergleich (vermeidet False-Positives wie ein fiktives
        // zukünftiges Recht "administration_light").
        $sql = "SELECT COUNT(*) FROM users WHERE is_active = 1 AND FIND_IN_SET('administration', permissions) > 0";
        $params = [];

        if ($excludingId !== null) {
            $sql .= ' AND id != :excluding_id';
            $params['excluding_id'] = $excludingId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function hydrate(array $row): User
    {
        $permissions = $row['permissions'] === '' || $row['permissions'] === null
            ? []
            : array_values(array_filter(array_map(
                static fn (string $value) => Permission::tryFrom($value),
                explode(',', $row['permissions']),
            )));

        return new User(
            id: $row['id'],
            email: $row['email'],
            passwordHash: $row['password_hash'],
            firstName: $row['first_name'] ?? null,
            lastName: $row['last_name'] ?? null,
            permissions: $permissions,
            twoFactorMethod: TwoFactorMethod::from($row['two_factor_method']),
            totpSecret: $row['totp_secret'],
            mustChangePassword: (bool) $row['must_change_password'],
            mustSetupTwoFactor: (bool) ($row['must_setup_two_factor'] ?? false),
            isActive: (bool) $row['is_active'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            defaultPageSize: isset($row['default_page_size']) ? (int) $row['default_page_size'] : 25,
            locale: isset($row['locale']) && $row['locale'] !== '' ? (string) $row['locale'] : 'de',
            notifyOnNewReceiptEmail: (bool) ($row['notify_on_new_receipt_email'] ?? false),
        );
    }
}
