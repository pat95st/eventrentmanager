<?php

declare(strict_types=1);

namespace App\Application\Article\Channel;

/** Ergebnis eines erfolgreichen ArticleChannelPublisherInterface::publish()-Aufrufs. */
final class ArticleChannelPublishResult
{
    public function __construct(
        public readonly string $externalId,
        public readonly ?string $externalLink,
    ) {
    }
}
