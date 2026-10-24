<?php

declare(strict_types=1);

namespace App\Application\NoticeDocument\Command;

use App\Application\Command\CommandInterface;

final class DeleteNoticeDocument implements CommandInterface
{
    public function __construct(public readonly string $noticeDocumentId)
    {
    }
}
