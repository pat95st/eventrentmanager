<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Query;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\CaseContent\CaseContentItem;
use App\Domain\Manufacturer\Manufacturer;

final class CaseContentItemWithArticle
{
    public function __construct(
        public readonly CaseContentItem $item,
        public readonly Article $article,
        public readonly ?Manufacturer $manufacturer = null,
    ) {
    }

    // "Hersteller Artikelbezeichnung" (Nutzerwunsch: Hersteller immer vor
    // der Artikelbezeichnung) für den Case-Inhalt.
    public function displayName(): string
    {
        return ArticleDisplayName::of($this->article, $this->manufacturer);
    }
}
