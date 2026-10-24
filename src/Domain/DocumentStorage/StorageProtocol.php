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

    public function label(): string
    {
        return match ($this) {
            self::WebDav => 'WebDAV',
            self::Ftps => 'FTPS',
            self::Sftp => 'SFTP',
            self::Scp => 'SCP',
            self::S3 => 'S3 (kompatibler Objektspeicher)',
        };
    }
}
