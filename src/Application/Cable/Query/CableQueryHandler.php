<?php

declare(strict_types=1);

namespace App\Application\Cable\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Cable\CableRepositoryInterface;

/** Beantwortet GetCableSettings und GetArticleCable (Migration 125). */
final class CableQueryHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CableRepositoryInterface $cables)
    {
    }

    public function handle(QueryInterface $query): mixed
    {
        if ($query instanceof GetArticleCable) {
            $cable = $this->cables->findByArticle($query->articleId);

            return $cable === null ? null : new ArticleCableDetail(
                $cable,
                $this->cables->findTypeById($cable->cableTypeId),
                $this->cables->findConnectorById($cable->connectorAId),
                $this->cables->findConnectorById($cable->connectorBId),
            );
        }
        assert($query instanceof GetCableSettings);

        return new CableSettings($this->cables->findAllTypes(), $this->cables->findAllConnectors(), $this->cables->usageCounts());
    }
}
