<?php

declare(strict_types=1);

namespace App\Application\NoticeDocument\Query;

use App\Application\Query\QueryInterface;

final class GetNoticeDocumentsForArticleIds implements QueryInterface
{
    /** @param string[] $articleIds */
    public function __construct(public readonly array $articleIds)
    {
    }
}
