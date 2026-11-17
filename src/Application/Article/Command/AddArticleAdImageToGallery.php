<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandInterface;

/**
 * Copies an AI ad image (see ArticleAdImage) into the article's regular
 * product gallery (ArticleImage). The ad image itself stays untouched.
 */
final class AddArticleAdImageToGallery implements CommandInterface
{
    public function __construct(
        public readonly string $adImageId,
    ) {
    }
}
