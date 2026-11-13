<?php

declare(strict_types=1);

namespace App\Application\DigitalStagebox\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\DigitalStagebox\DigitalStageboxRepositoryInterface;

/** Beantwortet GetDigitalStageboxSettings und GetArticleDigitalStagebox (Migration 152). */
final class DigitalStageboxQueryHandler implements QueryHandlerInterface
{
    public function __construct(private readonly DigitalStageboxRepositoryInterface $stageboxes)
    {
    }

    public function handle(QueryInterface $query): mixed
    {
        if ($query instanceof GetArticleDigitalStagebox) {
            $stagebox = $this->stageboxes->findByArticle($query->articleId);

            return $stagebox === null ? null : new ArticleDigitalStageboxDetail($stagebox, $this->stageboxes->findProtocolById($stagebox->protocolId));
        }
        assert($query instanceof GetDigitalStageboxSettings);

        return new DigitalStageboxSettings($this->stageboxes->findAllProtocols(), $this->stageboxes->usageCounts());
    }
}
