<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\StorageProtocol;

/** Wählt anhand von DocumentStorageTarget::$protocol den passenden Protokoll-Client. */
final class StorageClientFactory
{
    public function create(DocumentStorageTarget $target): StorageClientInterface
    {
        return match ($target->protocol) {
            StorageProtocol::WebDav => new WebDavClient($target),
            StorageProtocol::Ftps => new FtpsClient($target),
            StorageProtocol::Sftp => new SftpClient($target),
            StorageProtocol::Scp => new ScpClient($target),
            StorageProtocol::S3 => new S3Client($target),
        };
    }
}
