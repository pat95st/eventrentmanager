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
        };
    }

    /** Nextcloud/ownCloud: Ordner lassen sich per Freigabe-Link teilen (OCS-Share-API). */
    public function supportsSharing(): bool
    {
        return $this === self::Nextcloud || $this === self::OwnCloud;
    }

    /** Wird über WebDAV angesprochen (siehe App\Infrastructure\DocumentStorage\WebDavClient). */
    public function usesWebDav(): bool
    {
        return $this === self::WebDav || $this->supportsSharing();
    }
}
