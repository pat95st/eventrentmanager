<?php

declare(strict_types=1);

namespace App\Domain\AnalogMulticore;

/** Analog multicore details of articles (migration 156). */
interface AnalogMulticoreRepositoryInterface
{
    public function findByArticle(string $articleId): ?ArticleAnalogMulticore;

    public function save(ArticleAnalogMulticore $multicore): void;

    public function delete(string $articleId): void;
}
