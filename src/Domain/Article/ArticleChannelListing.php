<?php

declare(strict_types=1);

namespace App\Domain\Article;

use DateTimeImmutable;

/**
 * Bildet ab, ob und wie ein Artikel an einen externen Vertriebskanal
 * (Google Merchant Center, Google Unternehmensprofil, WhatsApp Business)
 * übermittelt wird — je Artikel und Kanal genau ein Datensatz (siehe
 * UNIQUE KEY in Migration 059). $externalLink ist der Link, der in der
 * Artikelverwaltung als "Link zum Artikel" angezeigt wird.
 */
final class ArticleChannelListing
{
    public function __construct(
        public readonly string $id,
        public readonly string $articleId,
        public readonly SalesChannel $channel,
        public bool $enabled,
        public ?string $externalId,
        public ?string $externalLink,
        public ChannelSyncStatus $status,
        public ?DateTimeImmutable $lastSyncedAt,
        public ?string $lastError,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
