<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Article\Channel\ArticleChannelPublisherRegistry;
use App\Application\Article\Channel\ArticleChannelUrlBuilder;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleChannelListingRepositoryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Article\ChannelSyncException;
use App\Domain\Article\ChannelSyncStatus;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class SyncArticleChannelHandler implements CommandHandlerInterface
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
        assert($command instanceof SyncArticleChannel);

        $article = $this->articles->findById($command->articleId);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$command->articleId}\" wurde nicht gefunden.");
        }

        $listing = $this->listings->findByArticleAndChannel($article->id, $command->channel);
        if ($listing === null || !$listing->enabled) {
            throw new ValidationException(['channel' => 'Der Kanal ist nicht aktiviert.']);
        }

        $publisher = $this->publishers->for($command->channel);

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
