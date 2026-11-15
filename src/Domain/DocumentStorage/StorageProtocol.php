<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

/** Übertragungsprotokoll eines Ziellaufwerks, siehe DocumentStorageTarget. */
enum StorageProtocol: string
{
    case WebDav = 'webdav';
    case Ftps = 'ftps';
    case Sftp = 'sftp';
    case Scp = 'scp';
    case S3 = 's3';
    // Migration 148: WebDAV + Freigaben über die OCS-Share-API.
    case Nextcloud = 'nextcloud';
    case OwnCloud = 'owncloud';
    // Migration 153: cloud storage services connected via OAuth 2.0.
    case Dropbox = 'dropbox';
    case OneDrive = 'onedrive';
    case GoogleDrive = 'google_drive';
    case Box = 'box';
    // Migration 153: iCloud Drive has no public file API for third-party
    // servers, so it is written to as a locally synchronised folder.
    case ICloud = 'icloud';

    public function label(): string
    {
        return match ($this) {
            self::WebDav => 'WebDAV',
            self::Ftps => 'FTPS',
            self::Sftp => 'SFTP',
            self::Scp => 'SCP',
            self::S3 => 'S3 (kompatibler Objektspeicher)',
            self::Nextcloud => 'Nextcloud',
            self::OwnCloud => 'ownCloud',
            self::Dropbox => 'Dropbox',
            self::OneDrive => 'Microsoft OneDrive',
            self::GoogleDrive => 'Google Drive',
            self::Box => 'Box',
            self::ICloud => 'iCloud Drive (synchronisierter Ordner)',
        };
    }

    /** Nextcloud/ownCloud: Ordner lassen sich per Freigabe-Link teilen (OCS-Share-API). */
    public function supportsSharing(): bool
    {
        return $this === self::Nextcloud || $this === self::OwnCloud;
    }

    /** Nextcloud teilt Ordner selbst per E-Mail (Freigabetyp "E-Mail"); ownCloud kennt nur Freigabe-Links. */
    public function supportsNativeMailShare(): bool
    {
        return $this === self::Nextcloud;
    }

    /** Lässt sich ein angelegter Ordner nachträglich umbenennen/verschieben? S3 kennt kein Umbenennen. */
    public function supportsFolderRename(): bool
    {
        return $this !== self::S3;
    }

    /** Lässt sich ein Ordner samt Inhalt löschen? Für FTPS und S3 nicht umgesetzt. */
    public function supportsFolderDelete(): bool
    {
        return $this->usesWebDav()
            || $this === self::Sftp
            || $this === self::Scp
            || $this->usesOAuth()
            || $this === self::ICloud;
    }

    /** Wird über WebDAV angesprochen (siehe App\Infrastructure\DocumentStorage\WebDavClient). */
    public function usesWebDav(): bool
    {
        return $this === self::WebDav || $this->supportsSharing();
    }

    /**
     * Cloud service that is connected via the OAuth 2.0 authorization code
     * flow (client ID/secret of an app registered by the administrator,
     * then "Verbinden" in the settings), see
     * App\Infrastructure\DocumentStorage\Cloud\CloudOAuthEndpoints.
     */
    public function usesOAuth(): bool
    {
        return match ($this) {
            self::Dropbox, self::OneDrive, self::GoogleDrive, self::Box => true,
            default => false,
        };
    }

    /** Writes into a directory on the application server (iCloud Drive sync folder). */
    public function usesLocalDirectory(): bool
    {
        return $this === self::ICloud;
    }

    /** Needs host/port (and usually username/password) of a server. */
    public function usesServerConnection(): bool
    {
        return !$this->usesOAuth() && !$this->usesLocalDirectory() && $this !== self::S3;
    }
}
