<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandInterface;

final class CreateStorageTarget implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $protocol,
        public readonly string $host,
        public readonly string $port,
        public readonly string $username,
        public readonly ?string $password,
        public readonly ?string $privateKey,
        public readonly ?string $privateKeyPassphrase,
        public readonly string $basePath,
        public readonly bool $webdavUseHttps,
        public readonly bool $webdavAllowSelfSigned,
        public readonly string $s3AccessKeyId,
        public readonly ?string $s3SecretAccessKey,
        public readonly string $s3Bucket,
        public readonly string $s3Region,
        public readonly string $s3Endpoint,
        public readonly bool $s3UsePathStyle,
        public readonly bool $enabled,
    ) {
    }
}
