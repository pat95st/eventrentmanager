<?php

declare(strict_types=1);

namespace App\Application\Rma\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Rma\RmaDocumentRepositoryInterface;
use App\Domain\Rma\RmaRepositoryInterface;

/**
 * Artikelübergreifende RMA-Übersicht (Grundlage für die globale RMA-Seite
 * im Menü), optional gefiltert nach Status und/oder Suchbegriff.
 */
final class ListRmasHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RmaRepositoryInterface $rmas,
        private readonly RmaDocumentRepositoryInterface $rmaDocuments,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
    ) {
    }

    /** @return RmaOverviewEntry[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListRmas);

        $entries = [];

        foreach ($this->rmas->findAll() as $rma) {
            $inventoryNumber = $this->inventoryNumbers->findById($rma->inventoryNumberId);
            if ($inventoryNumber === null) {
                continue;
            }

            $article = $this->articles->findById($inventoryNumber->articleId);
            if ($article === null) {
                continue;
            }

            $manufacturer = $this->manufacturers->findById(($article->manufacturerId ?? ''));
            if ($manufacturer === null) {
                continue;
            }

            if ($query->status !== null && $rma->status() !== $query->status) {
                continue;
            }

            if ($query->searchTerm !== null && $query->searchTerm !== '' && !$this->matchesSearch($query->searchTerm, $article->name, $manufacturer->name, $inventoryNumber->number)) {
                continue;
            }

            $entries[] = new RmaOverviewEntry(
                rma: $rma,
                inventoryNumber: $inventoryNumber,
                article: $article,
                manufacturer: $manufacturer,
                documentCount: count($this->rmaDocuments->findByRma($rma->id)),
            );
        }

        return $entries;
    }

    private function matchesSearch(string $term, string ...$haystacks): bool
    {
        $needle = mb_strtolower($term);
        foreach ($haystacks as $haystack) {
            if (str_contains(mb_strtolower($haystack), $needle)) {
                return true;
            }
        }

        return false;
    }
}
