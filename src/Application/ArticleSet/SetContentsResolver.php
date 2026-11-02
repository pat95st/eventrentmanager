<?php

declare(strict_types=1);

namespace App\Application\ArticleSet;

use App\Application\ArticleSet\Query\ArticleSetDetail;
use App\Application\ArticleSet\Query\GetArticleSet;
use App\Application\ArticleSet\Query\GetArticleSetHandler;
use App\Domain\ArticleSet\ArticleSetRepositoryInterface;
use App\Domain\Exception\NotFoundException;

/**
 * Inhaltszeile ("enthält: 2× …, 1× …") für Set- und Upgrade-Positionen in
 * Detailansichten und PDFs von Angebot, AB und Rechnung (Migration 113).
 */
final class SetContentsResolver
{
    /** @var array<string, ArticleSetDetail|null> */
    private array $details = [];

    public function __construct(
        private readonly GetArticleSetHandler $getSet,
        private readonly ArticleSetRepositoryInterface $sets,
        private readonly ArticleLabelResolver $labels,
    ) {
    }

    /**
     * @param iterable<object> $freeTextItems Elemente mit id, setId, setUpgradeId
     * @return array<string, string> Freitext-Positions-ID => Inhaltszeile
     */
    public function resolve(iterable $freeTextItems): array
    {
        $result = [];
        foreach ($freeTextItems as $item) {
            if (($item->setId ?? null) !== null) {
                $detail = $this->detail($item->setId);
                if ($detail !== null && $detail->items !== []) {
                    $result[$item->id] = 'enthält: ' . $detail->contentsDescription();
                }
            } elseif (($item->setUpgradeId ?? null) !== null) {
                $upgrade = $this->sets->findUpgradeById($item->setUpgradeId);
                if ($upgrade !== null && $upgrade->items !== []) {
                    $result[$item->id] = 'enthält: ' . ArticleSetDetail::describe(array_map(
                        fn ($upgradeItem) => ['label' => $this->labels->label($upgradeItem->articleId), 'quantity' => $upgradeItem->quantity],
                        $upgrade->items,
                    ));
                }
            }
        }

        return $result;
    }

    private function detail(string $setId): ?ArticleSetDetail
    {
        if (!array_key_exists($setId, $this->details)) {
            try {
                $this->details[$setId] = $this->getSet->handle(new GetArticleSet($setId));
            } catch (NotFoundException) {
                $this->details[$setId] = null;
            }
        }

        return $this->details[$setId];
    }
}
