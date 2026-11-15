<?php

declare(strict_types=1);

namespace App\Application\Backup\Query;

use App\Application\Query\QueryInterface;

/** The most recent automatic backup runs, newest first. */
final class ListBackupRuns implements QueryInterface
{
    public function __construct(public readonly int $limit = 10)
    {
    }
}
