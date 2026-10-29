<?php

declare(strict_types=1);

namespace App\Application\Article\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Article\FirmwareVersionRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;

final class GetInventoryNumberContextHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly FirmwareVersionRepositoryInterface $firmwareVersions,
    ) {
    }

    public function handle(QueryInterface $query): InventoryNumberContext
    {
        assert($query instanceof GetInventoryNumberContext);

        $inventoryNumber = $this->inventoryNumbers->findById($query->inventoryNumberId);
        if ($inventoryNumber === null) {
            throw new NotFoundException("Inventurnummer \"{$query->inventoryNumberId}\" wurde nicht gefunden.");
        }

        $article = $this->articles->findById($inventoryNumber->articleId);
        if ($article === null) {
            throw new NotFoundException('Zugehöriger Artikel wurde nicht gefunden.');
        }

        $manufacturer = $this->manufacturers->findById(($article->manufacturerId ?? ''));
        if ($manufacturer === null) {
            throw new NotFoundException('Hersteller des Artikels wurde nicht gefunden.');
        }

        $installedFirmwareVersion = null;
        $firmwareOutdated = false;
        if ($article->hasFirmware && $inventoryNumber->installedFirmwareVersionId !== null) {
            $installedFirmwareVersion = $this->firmwareVersions->findById($inventoryNumber->installedFirmwareVersionId);
            $latestFirmwareVersion = $this->firmwareVersions->findLatestForArticle($article->id);
            $firmwareOutdated = $installedFirmwareVersion !== null
                && $latestFirmwareVersion !== null
                && $installedFirmwareVersion->id !== $latestFirmwareVersion->id;
        }

        return new InventoryNumberContext($inventoryNumber, $article, $manufacturer, $installedFirmwareVersion, $firmwareOutdated);
    }
}
