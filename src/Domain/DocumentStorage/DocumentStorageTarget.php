<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

use DateTimeImmutable;

/**
 * Ein vom Administrator verbundenes externes Speicherziel ("Ziellaufwerk"),
 * z. B. eine Nextcloud-Instanz (WebDAV), ein FTPS-/SFTP-/SCP-Server, ein
 * S3-kompatibler Objektspeicher, ein per OAuth verbundener Cloud-Dienst
 * (Dropbox, OneDrive, Google Drive, Box) oder ein lokal synchronisierter
 * iCloud-Drive-Ordner. Alle protokollspezifischen Felder liegen
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
        // Migration 153: OAuth 2.0 connection for Dropbox, OneDrive, Google
        // Drive and Box (see StorageProtocol::usesOAuth()). Client ID/secret
        // belong to the app the administrator registered with the provider;
        // the tokens are filled in by "Verbinden" and refreshed automatically.
        public ?string $oauthClientId = null,
        public ?string $oauthClientSecret = null,
        // OneDrive only: Microsoft Entra tenant ("common" when empty).
        public ?string $oauthTenant = null,
        public ?string $oauthRefreshToken = null,
        public ?string $oauthAccessToken = null,
        public ?DateTimeImmutable $oauthAccessTokenExpiresAt = null,
        // Name/e-mail of the connected account, for display only.
        public ?string $oauthAccountLabel = null,
        public ?DateTimeImmutable $oauthConnectedAt = null,
    ) {
    }

    /** OAuth services: has an account been connected (refresh token present)? */
    public function isOAuthConnected(): bool
    {
        return $this->protocol->usesOAuth() && $this->oauthRefreshToken !== null && $this->oauthRefreshToken !== '';
    }

    /** Drops all tokens of the OAuth connection (client ID/secret stay). */
    public function disconnectOAuth(): void
    {
        $this->oauthRefreshToken = null;
        $this->oauthAccessToken = null;
        $this->oauthAccessTokenExpiresAt = null;
        $this->oauthAccountLabel = null;
        $this->oauthConnectedAt = null;
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
