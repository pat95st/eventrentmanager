<?php

declare(strict_types=1);

namespace App\Application\Dunning\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Dunning\DunningLevelRepositoryInterface;

final class ListDunningLevelsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly DunningLevelRepositoryInterface $dunningLevels)
    {
    }

    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListDunningLevels);

        return $this->dunningLevels->findAllOrdered();
    }
}
