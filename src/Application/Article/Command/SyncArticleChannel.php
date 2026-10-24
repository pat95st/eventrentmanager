<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandInterface;
use App\Domain\Article\SalesChannel;

/** Manueller "Erneut synchronisieren"-Versuch für einen bereits aktivierten Kanal, siehe SyncArticleChannelHandler. */
final class SyncArticleChannel implements CommandInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly SalesChannel $channel,
    ) {
    }
}
