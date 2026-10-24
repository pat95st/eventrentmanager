<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Article\ArticleChannelListing;
use App\Domain\Article\ArticleChannelListingRepositoryInterface;
use App\Domain\Article\ChannelSyncStatus;
use App\Domain\Article\SalesChannel;
use DateTimeImmutable;
use PDO;

final class MariadbArticleChannelListingRepository implements ArticleChannelListingRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByArticle(string $articleId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_channel_listings WHERE article_id = :article_id');
        $stmt->execute(['article_id' => $articleId]);

        $listings = [];
        foreach ($stmt->fetchAll() as $row) {
            $listing = $this->hydrate($row);
            $listings[$listing->channel->value] = $listing;
        }

        return $listings;
    }

    public function findByArticleAndChannel(string $articleId, SalesChannel $channel): ?ArticleChannelListing
    {
        $stmt = $this->pdo->prepare('SELECT * FROM article_channel_listings WHERE article_id = :article_id AND channel = :channel');
        $stmt->execute(['article_id' => $articleId, 'channel' => $channel->value]);
        $row = $stmt->fetch();

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function save(ArticleChannelListing $listing): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO article_channel_listings
                (id, article_id, channel, enabled, external_id, external_link, status, last_synced_at, last_error, updated_at)
             VALUES
                (:id, :article_id, :channel, :enabled, :external_id, :external_link, :status, :last_synced_at, :last_error, :updated_at)
             ON DUPLICATE KEY UPDATE
                enabled = VALUES(enabled),
                external_id = VALUES(external_id),
                external_link = VALUES(external_link),
                status = VALUES(status),
                last_synced_at = VALUES(last_synced_at),
                last_error = VALUES(last_error),
                updated_at = VALUES(updated_at)'
        );

        $stmt->execute([
            'id' => $listing->id,
            'article_id' => $listing->articleId,
            'channel' => $listing->channel->value,
            'enabled' => $listing->enabled ? 1 : 0,
            'external_id' => $listing->externalId,
            'external_link' => $listing->externalLink,
            'status' => $listing->status->value,
            'last_synced_at' => $listing->lastSyncedAt?->format('Y-m-d H:i:s'),
            'last_error' => $listing->lastError,
            'updated_at' => $listing->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ArticleChannelListing
    {
        return new ArticleChannelListing(
            id: $row['id'],
            articleId: $row['article_id'],
            channel: SalesChannel::from($row['channel']),
            enabled: (bool) $row['enabled'],
            externalId: $row['external_id'],
            externalLink: $row['external_link'],
            status: ChannelSyncStatus::from($row['status']),
            lastSyncedAt: $row['last_synced_at'] !== null ? new DateTimeImmutable($row['last_synced_at']) : null,
            lastError: $row['last_error'],
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
