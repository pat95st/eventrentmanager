<?php

declare(strict_types=1);

namespace App\Application\NoticeDocument\Command;

use App\Application\Command\CommandInterface;

final class UploadNoticeDocument implements CommandInterface
{
    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $uploadedFile
     * @param string[] $articleIds
     */
    public function __construct(
        public readonly string $name,
        public readonly array $uploadedFile,
        public readonly array $articleIds,
    ) {
    }
}
