<?php

declare(strict_types=1);

namespace App\Application\AddressBook\Query;

use App\Application\Query\QueryInterface;

final class ListCardDavCredentialsForUser implements QueryInterface
{
    public function __construct(public readonly string $userId)
    {
    }
}
