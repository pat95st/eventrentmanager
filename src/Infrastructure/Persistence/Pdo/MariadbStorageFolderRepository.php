<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\DocumentStorage\SharePermission;
use App\Domain\DocumentStorage\StorageFolder;
use App\Domain\DocumentStorage\StorageFolderOwnerType;
use App\Domain\DocumentStorage\StorageFolderRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbStorageFolderRepository implements StorageFolderRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(StorageFolder $folder): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO storage_folders (
                id, rental_id, customer_id, storage_target_id, path, in_customer_folder,
                share_id, share_url, share_permission, share_has_password, share_mailed_at, share_mailed_to,
                created_at, updated_at
             ) VALUES (
                :id, :rental_id, :customer_id, :storage_target_id, :path, :in_customer_folder,
                :share_id, :share_url, :share_permission, :share_has_password, :share_mailed_at, :share_mailed_to,
                :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                path = VALUES(path),
                share_id = VALUES(share_id), share_url = VALUES(share_url),
                share_permission = VALUES(share_permission), share_has_password = VALUES(share_has_password),
                share_mailed_at = VALUES(share_mailed_at), share_mailed_to = VALUES(share_mailed_to),
                updated_at = VALUES(updated_at)'
        );

        $stmt->execute([
            'id' => $folder->id,
            'rental_id' => $folder->ownerType === StorageFolderOwnerType::Rental ? $folder->ownerId : null,
            'customer_id' => $folder->ownerType === StorageFolderOwnerType::Customer ? $folder->ownerId : null,
            'storage_target_id' => $folder->storageTargetId,
            'path' => $folder->path,
            'in_customer_folder' => $folder->inCustomerFolder ? 1 : 0,
            'share_id' => $folder->shareId,
            'share_url' => $folder->shareUrl,
            'share_permission' => $folder->sharePermission?->value,
            'share_has_password' => $folder->shareHasPassword ? 1 : 0,
            'share_mailed_at' => $folder->shareMailedAt?->format('Y-m-d H:i:s'),
            'share_mailed_to' => $folder->shareMailedTo,
            'created_at' => $folder->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $folder->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByOwner(StorageFolderOwnerType $ownerType, string $ownerId): ?StorageFolder
    {
        $column = $ownerType === StorageFolderOwnerType::Rental ? 'rental_id' : 'customer_id';
        $stmt = $this->pdo->prepare("SELECT * FROM storage_folders WHERE {$column} = :owner_id");
        $stmt->execute(['owner_id' => $ownerId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function customerFolders(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM storage_folders WHERE customer_id IS NOT NULL');

        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(string) $row['customer_id']] = $this->hydrate($row);
        }

        return $result;
    }

    public function replacePathPrefix(string $storageTargetId, string $oldPath, string $newPath): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE storage_folders
                SET path = CONCAT(:new_path, SUBSTRING(path, :offset))
              WHERE storage_target_id = :target_id AND LEFT(path, :prefix_length) = :prefix'
        );
        $stmt->execute([
            'new_path' => $newPath,
            'offset' => mb_strlen($oldPath) + 1,
            'target_id' => $storageTargetId,
            'prefix_length' => mb_strlen($oldPath) + 1,
            'prefix' => $oldPath . '/',
        ]);
    }

    public function deleteBelowPath(string $storageTargetId, string $path): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM storage_folders WHERE storage_target_id = :target_id AND LEFT(path, :prefix_length) = :prefix'
        );
        $stmt->execute([
            'target_id' => $storageTargetId,
            'prefix_length' => mb_strlen($path) + 1,
            'prefix' => $path . '/',
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM storage_folders WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): StorageFolder
    {
        $isRental = $row['rental_id'] !== null;

        return new StorageFolder(
            id: $row['id'],
            ownerType: $isRental ? StorageFolderOwnerType::Rental : StorageFolderOwnerType::Customer,
            ownerId: (string) ($isRental ? $row['rental_id'] : $row['customer_id']),
            storageTargetId: $row['storage_target_id'],
            path: $row['path'],
            inCustomerFolder: (bool) $row['in_customer_folder'],
            shareId: $row['share_id'],
            shareUrl: $row['share_url'],
            sharePermission: $row['share_permission'] !== null ? SharePermission::from($row['share_permission']) : null,
            shareHasPassword: (bool) $row['share_has_password'],
            shareMailedAt: $row['share_mailed_at'] !== null ? new DateTimeImmutable($row['share_mailed_at']) : null,
            shareMailedTo: $row['share_mailed_to'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
