<?php

declare(strict_types=1);

namespace App\Application\Article\Query;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleAccessory;
use App\Domain\Article\ArticleAdImage;
use App\Domain\Article\ArticlePreIssueCheck;
use App\Domain\Article\FirmwareVersion;
use App\Domain\Category\Category;
use App\Domain\Manufacturer\Manufacturer;
use App\Domain\NoticeDocument\NoticeDocument;

/**
 * Schreibgeschütztes Lesemodell für die Artikel-Detailseite — bündelt den
 * Artikel mit den bereits aufgelösten Fremdschlüsseln (CQRS-Query-Seite).
 */
final class ArticleDetail
{
    /**
     * @param InventoryNumberWithRmaInfo[] $inventoryNumbers
     * @param ArticleAccessory[] $accessories
     * @param \App\Domain\Article\ArticleChannelListing[] $channelListings indiziert nach Channel-Value
     * @param FirmwareVersion[] $firmwareVersions nur relevant, wenn article->hasFirmware; neueste zuerst
     * @param ArticlePreIssueCheck[] $preIssueChecks
     * @param ArticleAdImage[] $adImages neueste zuerst
     * @param NoticeDocument[] $noticeDocuments zugeordnete Hinweisdokumente (z. B. Bedienungsanleitungen), sortiert nach Name
     */
    public function __construct(
        public readonly Article $article,
        public readonly ?Manufacturer $manufacturer, // null bei Bundles (Migration 111)
        public readonly ?Category $category,
        public readonly array $inventoryNumbers,
        // Vorschau der als nächstes vergebenen Inventurnummer; null, wenn Hersteller-
        // und/oder Artikel-Präfix noch fehlen (siehe InventoryNumberGenerator).
        public readonly ?string $nextInventoryNumberPreview,
        public readonly array $accessories,
        public readonly array $channelListings = [],
        public readonly array $firmwareVersions = [],
        public readonly array $preIssueChecks = [],
        public readonly array $adImages = [],
        public readonly array $noticeDocuments = [],
        /** @var list<array{document: NoticeDocument, sourceArticleId: string, sourceLabel: string}> Bundles: Dokumente der Bestandteile */
        public readonly array $inheritedNoticeDocuments = [],
    ) {
    }
}
