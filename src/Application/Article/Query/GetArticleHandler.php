<?php

declare(strict_types=1);

namespace App\Application\Article\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleAccessoryRepositoryInterface;
use App\Domain\Article\ArticleChannelListingRepositoryInterface;
use App\Domain\Article\ArticlePreIssueCheckRepositoryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Article\FirmwareVersionRepositoryInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\InventoryNumber\InventoryNumberGenerator;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Rma\RmaRepositoryInterface;
use App\Domain\Rma\RmaStatus;
use App\Domain\Sale\SaleRepositoryInterface;

final class GetArticleHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly CategoryRepositoryInterface $categories,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly RmaRepositoryInterface $rmas,
        private readonly ArticleAccessoryRepositoryInterface $accessories,
        private readonly ArticlePreIssueCheckRepositoryInterface $preIssueChecks,
        private readonly ArticleChannelListingRepositoryInterface $channelListings,
        private readonly SaleRepositoryInterface $sales,
        private readonly FirmwareVersionRepositoryInterface $firmwareVersions,
    ) {
    }

    public function handle(QueryInterface $query): ArticleDetail
    {
        assert($query instanceof GetArticle);

        $article = $this->articles->findById($query->id);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$query->id}\" wurde nicht gefunden.");
        }

        $manufacturer = $this->manufacturers->findById($article->manufacturerId);
        if ($manufacturer === null) {
            throw new NotFoundException("Hersteller des Artikels wurde nicht gefunden.");
        }

        $category = $article->categoryId !== null ? $this->categories->findById($article->categoryId) : null;

        $rawInventoryNumbers = $this->inventoryNumbers->findByArticle($article->id);

        $firmwareVersions = $article->hasFirmware ? $this->firmwareVersions->findAllForArticle($article->id) : [];
        $firmwareVersionsById = [];
        foreach ($firmwareVersions as $firmwareVersion) {
            $firmwareVersionsById[$firmwareVersion->id] = $firmwareVersion;
        }
        $latestFirmwareVersion = $article->hasFirmware ? $this->firmwareVersions->findLatestForArticle($article->id) : null;

        $inventoryNumbers = array_map(
            function ($inventoryNumber) use ($firmwareVersionsById, $latestFirmwareVersion) {
                $rmas = $this->rmas->findByInventoryNumber($inventoryNumber->id);
                $openCount = count(array_filter(
                    $rmas,
                    fn ($rma) => $rma->status() !== RmaStatus::Abgeschlossen
                ));

                $installedFirmwareVersion = $inventoryNumber->installedFirmwareVersionId !== null
                    ? ($firmwareVersionsById[$inventoryNumber->installedFirmwareVersionId] ?? null)
                    : null;

                return new InventoryNumberWithRmaInfo(
                    inventoryNumber: $inventoryNumber,
                    totalRmaCount: count($rmas),
                    openRmaCount: $openCount,
                    sale: $this->sales->findByInventoryNumber($inventoryNumber->id),
                    installedFirmwareVersion: $installedFirmwareVersion,
                    firmwareOutdated: $installedFirmwareVersion !== null
                        && $latestFirmwareVersion !== null
                        && $installedFirmwareVersion->id !== $latestFirmwareVersion->id,
                );
            },
            $rawInventoryNumbers
        );

        $nextInventoryNumberPreview = ($manufacturer->prefix !== null && $article->prefix !== null)
            ? InventoryNumberGenerator::nextNumber($rawInventoryNumbers, $manufacturer->prefix, $article->prefix)
            : null;

        $accessories = $this->accessories->findByArticle($article->id);
        $preIssueChecks = $this->preIssueChecks->findByArticle($article->id);
        $channelListings = $this->channelListings->findByArticle($article->id);

        return new ArticleDetail($article, $manufacturer, $category, $inventoryNumbers, $nextInventoryNumberPreview, $accessories, $channelListings, $firmwareVersions, $preIssueChecks);
    }
}
