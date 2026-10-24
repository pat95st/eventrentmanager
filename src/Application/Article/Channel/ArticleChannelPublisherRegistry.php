<?php

declare(strict_types=1);

namespace App\Application\Article\Channel;

use App\Domain\Article\SalesChannel;
use RuntimeException;

/** Ordnet jedem SalesChannel-Fall die passende ArticleChannelPublisherInterface-Implementierung zu. */
final class ArticleChannelPublisherRegistry
{
    /** @var array<string, ArticleChannelPublisherInterface> */
    private array $publishersByChannel = [];

    /** @param ArticleChannelPublisherInterface[] $publishers */
    public function __construct(array $publishers)
    {
        foreach ($publishers as $publisher) {
            $this->publishersByChannel[$publisher->channel()->value] = $publisher;
        }
    }

    public function for(SalesChannel $channel): ArticleChannelPublisherInterface
    {
        if (!isset($this->publishersByChannel[$channel->value])) {
            throw new RuntimeException("Für den Kanal \"{$channel->value}\" ist kein Publisher registriert.");
        }

        return $this->publishersByChannel[$channel->value];
    }
}
