<?php

declare(strict_types=1);

namespace App\Application\Network\Command;

use App\Application\Command\CommandInterface;

/** Network interface of an article (migration 155); ignored for cables and bundles. */
final class SaveArticleNetworkSettings implements CommandInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly bool $hasNetworkInterface,
        public readonly bool $requiresDhcp = false,
    ) {
    }
}
