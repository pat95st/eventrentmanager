<?php

declare(strict_types=1);

namespace App\Application\NoticeDocument\Command;

use App\Application\Command\CommandInterface;

final class UpdateNoticeDocumentAssignment implements CommandInterface
{
    /** @param string[] $articleIds */
    public function __construct(
        public readonly string $noticeDocumentId,
        public readonly string $name,
        public readonly array $articleIds,
    ) {
    }
}
