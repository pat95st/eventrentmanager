<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Query;

use App\Application\Query\QueryInterface;

final class ListStorageUploadLog implements QueryInterface
{
    public function __construct(public readonly int $limit = 100)
    {
    }
}
