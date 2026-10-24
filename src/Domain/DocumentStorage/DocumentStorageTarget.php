<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

use DateTimeImmutable;

/**
 * Ein vom Administrator verbundenes externes Speicherziel ("Ziellaufwerk"),
 * z. B. eine Nextcloud-Instanz (WebDAV), ein FTPS-/SFTP-/SCP-Server oder ein
 * S3-kompatibler Objektspeicher. Alle protokollspezifischen Felder liegen
 * bewusst in einer Klasse (analog zu label_printers, wo driverType ebenfalls
 * bestimmt, welche Felder tatsächlich genutzt werden) — siehe
 * App\Infrastructure\DocumentStorage\StorageClientFactory für die
 * Zuordnung protocol -> konkreter Client.
 */
final class DocumentStorageTarget
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public StorageProtocol $protocol,
        public ?string $host,
        public ?int $port,
        public ?string $username,
        public ?string $password,
        public ?string $privateKey,
        public ?string $privateKeyPassphrase,
        public ?string $basePath,
        public bool $webdavUseHttps,
        public bool $webdavAllowSelfSigned,
        public ?string $s3AccessKeyId,
        public ?string $s3SecretAccessKey,
        public ?string $s3Bucket,
        public ?string $s3Region,
        public ?string $s3Endpoint,
        public bool $s3UsePathStyle,
        public bool $enabled,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
