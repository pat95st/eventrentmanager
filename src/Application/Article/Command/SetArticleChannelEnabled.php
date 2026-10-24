<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandInterface;
use App\Domain\Article\SalesChannel;

final class SetArticleChannelEnabled implements CommandInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly SalesChannel $channel,
        public readonly bool $enabled,
    ) {
    }
}
