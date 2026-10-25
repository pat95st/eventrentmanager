<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\CaseContent\CaseContentItemRepositoryInterface;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberGenerator;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberRepositoryInterface;
use App\Domain\CaseItem\CaseItemRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;

final class GetCaseItemHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly CaseItemRepositoryInterface $cases,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly CaseInventoryNumberRepositoryInterface $caseInventoryNumbers,
        private readonly CaseContentItemRepositoryInterface $contentItems,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    public function handle(QueryInterface $query): CaseItemDetail
    {
        assert($query instanceof GetCaseItem);

        $caseItem = $this->cases->findById($query->id);
        if ($caseItem === null) {
            throw new NotFoundException("Case \"{$query->id}\" wurde nicht gefunden.");
        }

        $manufacturer = $this->manufacturers->findById($caseItem->manufacturerId);
        if ($manufacturer === null) {
            throw new NotFoundException('Hersteller des Case wurde nicht gefunden.');
        }

        $rawInventoryNumbers = $this->caseInventoryNumbers->findByCase($caseItem->id);

        // Siehe GetInvoiceHandler für die identische Begründung des
        // kleinen Hersteller-Caches. Der Hersteller eines im Case
        // enthaltenen Artikels ist unabhängig vom Hersteller des Case
        // selbst (siehe $manufacturer oben), daher ein eigener Cache.
        $articleManufacturerCache = [];
        $articleManufacturerFor = function (string $manufacturerId) use (&$articleManufacturerCache) {
            if (!array_key_exists($manufacturerId, $articleManufacturerCache)) {
                $articleManufacturerCache[$manufacturerId] = $this->manufacturers->findById($manufacturerId);
            }

            return $articleManufacturerCache[$manufacturerId];
        };

        $inventoryNumbers = array_map(
            function ($caseInventoryNumber) use ($articleManufacturerFor) {
                $contentItems = $this->contentItems->findByCaseInventoryNumber($caseInventoryNumber->id);

                $content = array_values(array_filter(array_map(
                    function ($item) use ($articleManufacturerFor) {
                        $article = $this->articles->findById($item->articleId);

                        // Artikel könnte theoretisch parallel gelöscht worden sein;
                        // dann blenden wir die Zeile einfach aus statt zu crashen.
                        return $article !== null ? new CaseContentItemWithArticle($item, $article, $articleManufacturerFor($article->manufacturerId)) : null;
                    },
                    $contentItems
                )));

                return new CaseInventoryNumberWithContent($caseInventoryNumber, $content);
            },
            $rawInventoryNumbers
        );

        $nextInventoryNumberPreview = ($manufacturer->prefix !== null && $caseItem->prefix !== null)
            ? CaseInventoryNumberGenerator::nextNumber($rawInventoryNumbers, $manufacturer->prefix, $caseItem->prefix)
            : null;

        return new CaseItemDetail($caseItem, $manufacturer, $inventoryNumbers, $nextInventoryNumberPreview);
    }
}
