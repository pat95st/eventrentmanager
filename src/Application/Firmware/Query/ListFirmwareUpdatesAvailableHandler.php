<?php

declare(strict_types=1);

namespace App\Application\Firmware\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Article\FirmwareVersionRepositoryInterface;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;

/**
 * Artikelübergreifende Übersicht aller Inventurnummern, deren installierte
 * Firmware-Version NICHT der aktuellsten im Artikel-Katalog hinterlegten
 * Version entspricht (siehe FirmwareVersionRepositoryInterface::findLatestForArticle()).
 * Inventurnummern ohne hinterlegte installierte Version werden bewusst NICHT
 * aufgeführt — ohne diese Angabe lässt sich kein Update-Bedarf feststellen.
 */
final class ListFirmwareUpdatesAvailableHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly FirmwareVersionRepositoryInterface $firmwareVersions,
    ) {
    }

    /** @return FirmwareUpdateEntry[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListFirmwareUpdatesAvailable);

        $entries = [];
        $latestByArticle = [];

        foreach ($this->inventoryNumbers->findAllWithInstalledFirmwareVersion() as $inventoryNumber) {
            $article = $this->articles->findById($inventoryNumber->articleId);
            if ($article === null || !$article->hasFirmware) {
                continue;
            }

            $latestVersion = $latestByArticle[$article->id]
                ??= $this->firmwareVersions->findLatestForArticle($article->id);
            if ($latestVersion === null) {
                continue;
            }

            if ($inventoryNumber->installedFirmwareVersionId === null || $inventoryNumber->installedFirmwareVersionId === $latestVersion->id) {
                continue;
            }

            $installedVersion = $this->firmwareVersions->findById($inventoryNumber->installedFirmwareVersionId);
            if ($installedVersion === null) {
                continue;
            }

            $manufacturer = $this->manufacturers->findById(($article->manufacturerId ?? ''));
            if ($manufacturer === null) {
                continue;
            }

            if ($query->searchTerm !== null && $query->searchTerm !== '' && !$this->matchesSearch($query->searchTerm, $article->name, $manufacturer->name, $inventoryNumber->number)) {
                continue;
            }

            $entries[] = new FirmwareUpdateEntry(
                inventoryNumber: $inventoryNumber,
                article: $article,
                manufacturer: $manufacturer,
                installedVersion: $installedVersion,
                latestVersion: $latestVersion,
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
