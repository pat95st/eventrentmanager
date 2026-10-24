<?php

declare(strict_types=1);

namespace App\Application\Offer\Query;

use App\Application\Query\QueryInterface;

final class GetOffer implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
