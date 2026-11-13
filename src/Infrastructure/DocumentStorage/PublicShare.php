<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

/** Ein über die Share-API angelegter öffentlicher Freigabe-Link. */
final class PublicShare
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
    ) {
    }
}
