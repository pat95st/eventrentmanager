<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandInterface;

/**
 * Sends an existing ad image back to the image model together with change
 * requests and stores the result(s) as new ad images (see
 * RefineArticleAdImageHandler).
 */
final class RefineArticleAdImage implements CommandInterface
{
    public function __construct(
        public readonly string $adImageId,
        public readonly string $instructions,
        // Number of variants to generate in one go (validated in the handler).
        public readonly int $count = 1,
    ) {
    }
}
