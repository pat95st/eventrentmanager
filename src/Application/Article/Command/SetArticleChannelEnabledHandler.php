<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Article\Channel\ArticleChannelPublisherRegistry;
use App\Application\Article\Channel\ArticleChannelUrlBuilder;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleChannelListing;
use App\Domain\Article\ArticleChannelListingRepositoryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Article\ChannelSyncException;
use App\Domain\Article\ChannelSyncStatus;
use App\Domain\Exception\NotFoundException;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Schaltet die Übermittlung eines Artikels an einen externen Vertriebskanal
 * ein/aus. Beim Einschalten wird sofort synchron versucht, den Artikel an
 * den Kanal zu übermitteln (siehe ArticleChannelPublisherInterface) — bei
 * größeren Artikelbeständen könnte das perspektivisch asynchron laufen,
 * die Anwendung hat aber (Stand jetzt) keine Job-Queue, und ein einzelner
 * REST-Aufruf pro Artikel ist schnell genug für eine synchrone Anfrage.
 * Schlägt die Übermittlung fehl, wird der Schalter dennoch als "enabled"
 * gespeichert (mit Status "failed" + Fehlermeldung) statt automatisch
 * wieder zurückgesetzt zu werden — so kann der Fehler behoben und über
 * SyncArticleChannel erneut versucht werden, ohne den Schalter neu setzen
 * zu müssen.
 */
final class SetArticleChannelEnabledHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly ArticleChannelListingRepositoryInterface $listings,
        private readonly ArticleChannelPublisherRegistry $publishers,
        private readonly ArticleChannelUrlBuilder $urlBuilder,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SetArticleChannelEnabled);

        $article = $this->articles->findById($command->articleId);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$command->articleId}\" wurde nicht gefunden.");
        }

        $listing = $this->listings->findByArticleAndChannel($article->id, $command->channel)
            ?? new ArticleChannelListing(
                id: Uuid::v4(),
                articleId: $article->id,
                channel: $command->channel,
                enabled: false,
                externalId: null,
                externalLink: null,
                status: ChannelSyncStatus::Pending,
                lastSyncedAt: null,
                lastError: null,
                updatedAt: new DateTimeImmutable(),
            );

        $publisher = $this->publishers->for($command->channel);

        if (!$command->enabled) {
            $publisher->unpublish($listing);
            $listing->enabled = false;
            $listing->externalId = null;
            $listing->externalLink = null;
            $listing->status = ChannelSyncStatus::Pending;
            $listing->lastError = null;
            $listing->updatedAt = new DateTimeImmutable();
            $this->listings->save($listing);

            return $listing;
        }

        $listing->enabled = true;

        try {
            $productUrl = $this->urlBuilder->productUrl($article);
            $imageUrl = $this->urlBuilder->imageUrl($article);
            $result = $publisher->publish($article, $productUrl, $imageUrl);

            $listing->externalId = $result->externalId;
            $listing->externalLink = $result->externalLink;
            $listing->status = ChannelSyncStatus::Synced;
            $listing->lastError = null;
            $listing->lastSyncedAt = new DateTimeImmutable();
        } catch (ChannelSyncException $e) {
            $listing->status = ChannelSyncStatus::Failed;
            $listing->lastError = $e->getMessage();
        }

        $listing->updatedAt = new DateTimeImmutable();
        $this->listings->save($listing);

        return $listing;
    }
}
