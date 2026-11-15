<?php

declare(strict_types=1);

namespace App\Domain\Network;

/** Network interface of an article (migration 155); not available for cables. */
final class ArticleNetworkSettings
{
    public function __construct(
        public readonly string $articleId,
        public bool $hasNetworkInterface = false,
        // The device needs a DHCP server; its address is then documented as DHCP reservation.
        public bool $requiresDhcp = false,
    ) {
    }
}
