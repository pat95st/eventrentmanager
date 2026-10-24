<?php

declare(strict_types=1);

namespace App\Application\NoticeDocument\Query;

use App\Domain\NoticeDocument\NoticeDocument;

final class NoticeDocumentDetail
{
    /** @param string[] $articleIds */
    public function __construct(
        public readonly NoticeDocument $document,
        public readonly array $articleIds,
    ) {
    }
}
