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
        // Migration 148: Ordner für Veranstaltungen/Kunden. Alle Felder mit
        // Standardwert, damit bestehende new DocumentStorageTarget(...)-
        // Aufrufstellen unverändert bleiben.
        public bool $foldersEnabled = false,
        public string $eventFolderTemplate = FolderPathTemplate::DEFAULT_EVENT,
        public string $customerFolderTemplate = FolderPathTemplate::DEFAULT_CUSTOMER,
        public string $eventSubfolderTemplate = FolderPathTemplate::DEFAULT_EVENT_SUBFOLDER,
        // Nur Nextcloud/ownCloud: Freigaben immer mit Passwort anlegen und
        // nach welcher Richtlinie es erzeugt wird.
        public bool $shareAlwaysPassword = false,
        public int $sharePasswordLength = SharePasswordPolicy::DEFAULT_LENGTH,
        public bool $sharePasswordUppercase = true,
        public bool $sharePasswordLowercase = true,
        public bool $sharePasswordDigits = true,
        public bool $sharePasswordSymbols = false,
    ) {
    }

    public function sharePasswordPolicy(): SharePasswordPolicy
    {
        return new SharePasswordPolicy(
            $this->sharePasswordLength,
            $this->sharePasswordUppercase,
            $this->sharePasswordLowercase,
            $this->sharePasswordDigits,
            $this->sharePasswordSymbols,
        );
    }

    /** Steht dieses Ziellaufwerk für neue Veranstaltungs-/Kundenordner zur Auswahl? */
    public function offersFolders(): bool
    {
        return $this->enabled && $this->foldersEnabled;
    }
}
