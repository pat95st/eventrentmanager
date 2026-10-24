<?php

declare(strict_types=1);

namespace App\Application\Article\Channel;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleChannelListing;
use App\Domain\Article\ChannelSyncException;
use App\Domain\Article\SalesChannel;

/**
 * Übermittelt einen Artikel an genau einen externen Vertriebskanal. Eine
 * Implementierung je SalesChannel-Fall, siehe ArticleChannelPublisherRegistry.
 */
interface ArticleChannelPublisherInterface
{
    public function channel(): SalesChannel;

    /**
     * @param string $productUrl Absolute URL der Produktlandingpage (Pflichtangabe der meisten Kanal-APIs)
     * @param string|null $imageUrl Absolute, öffentlich erreichbare Bild-URL des Artikels
     * @throws ChannelSyncException bei fehlender Konfiguration/fehlenden Artikeldaten oder einem API-Fehler
     */
    public function publish(Article $article, string $productUrl, ?string $imageUrl): ArticleChannelPublishResult;

    /**
     * Entfernt den Artikel wieder aus dem Kanal (best effort — wird beim
     * Deaktivieren des Kanal-Schalters aufgerufen). Implementierungen
     * dürfen Fehler intern loggen/ignorieren, aber nicht werfen, damit ein
     * Deaktivieren in der Oberfläche nie fehlschlägt.
     */
    public function unpublish(ArticleChannelListing $listing): void;
}
