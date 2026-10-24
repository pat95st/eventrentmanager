<?php

declare(strict_types=1);

namespace App\Application\Calendar\Query;

use App\Application\Query\QueryInterface;

final class ListUserIdsForCalendar implements QueryInterface
{
    public function __construct(public readonly string $calendarId)
    {
    }
}
