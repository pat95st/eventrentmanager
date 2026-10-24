<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Domain\DocumentStorage\StorageProtocol;
use DateTimeImmutable;
use PDO;

final class MariadbDocumentStorageTargetRepository implements DocumentStorageTargetRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(DocumentStorageTarget $target): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_storage_targets (
                id, name, protocol, host, port, username, password, private_key, private_key_passphrase,
                base_path, webdav_use_https, webdav_allow_self_signed,
                s3_access_key_id, s3_secret_access_key, s3_bucket, s3_region, s3_endpoint, s3_use_path_style,
                enabled, created_at, updated_at
             ) VALUES (
                :id, :name, :protocol, :host, :port, :username, :password, :private_key, :private_key_passphrase,
                :base_path, :webdav_use_https, :webdav_allow_self_signed,
                :s3_access_key_id, :s3_secret_access_key, :s3_bucket, :s3_region, :s3_endpoint, :s3_use_path_style,
                :enabled, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                name = VALUES(name), protocol = VALUES(protocol), host = VALUES(host), port = VALUES(port),
                username = VALUES(username), password = VALUES(password), private_key = VALUES(private_key),
                private_key_passphrase = VALUES(private_key_passphrase), base_path = VALUES(base_path),
                webdav_use_https = VALUES(webdav_use_https), webdav_allow_self_signed = VALUES(webdav_allow_self_signed),
                s3_access_key_id = VALUES(s3_access_key_id), s3_secret_access_key = VALUES(s3_secret_access_key),
                s3_bucket = VALUES(s3_bucket), s3_region = VALUES(s3_region), s3_endpoint = VALUES(s3_endpoint),
                s3_use_path_style = VALUES(s3_use_path_style), enabled = VALUES(enabled), updated_at = VALUES(updated_at)'
        );

        $stmt->execute([
            'id' => $target->id,
            'name' => $target->name,
            'protocol' => $target->protocol->value,
            'host' => $target->host,
            'port' => $target->port,
            'username' => $target->username,
            'password' => $target->password,
            'private_key' => $target->privateKey,
            'private_key_passphrase' => $target->privateKeyPassphrase,
            'base_path' => $target->basePath,
            'webdav_use_https' => $target->webdavUseHttps ? 1 : 0,
            'webdav_allow_self_signed' => $target->webdavAllowSelfSigned ? 1 : 0,
            's3_access_key_id' => $target->s3AccessKeyId,
            's3_secret_access_key' => $target->s3SecretAccessKey,
            's3_bucket' => $target->s3Bucket,
            's3_region' => $target->s3Region,
            's3_endpoint' => $target->s3Endpoint,
            's3_use_path_style' => $target->s3UsePathStyle ? 1 : 0,
            'enabled' => $target->enabled ? 1 : 0,
            'created_at' => $target->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $target->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?DocumentStorageTarget
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_storage_targets WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM document_storage_targets ORDER BY name ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM document_storage_targets WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): DocumentStorageTarget
    {
        return new DocumentStorageTarget(
            id: $row['id'],
            name: $row['name'],
            protocol: StorageProtocol::from($row['protocol']),
            host: $row['host'],
            port: $row['port'] !== null ? (int) $row['port'] : null,
            username: $row['username'],
            password: $row['password'],
            privateKey: $row['private_key'],
            privateKeyPassphrase: $row['private_key_passphrase'],
            basePath: $row['base_path'],
            webdavUseHttps: (bool) $row['webdav_use_https'],
            webdavAllowSelfSigned: (bool) $row['webdav_allow_self_signed'],
            s3AccessKeyId: $row['s3_access_key_id'],
            s3SecretAccessKey: $row['s3_secret_access_key'],
            s3Bucket: $row['s3_bucket'],
            s3Region: $row['s3_region'],
            s3Endpoint: $row['s3_endpoint'],
            s3UsePathStyle: (bool) $row['s3_use_path_style'],
            enabled: (bool) $row['enabled'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
