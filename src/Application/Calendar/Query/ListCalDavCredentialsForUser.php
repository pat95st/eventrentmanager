<?php

declare(strict_types=1);

namespace App\Application\Calendar\Query;

use App\Application\Query\QueryInterface;

final class ListCalDavCredentialsForUser implements QueryInterface
{
    public function __construct(public readonly string $userId)
    {
    }
}
