<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandInterface;

final class GenerateArticleAdImage implements CommandInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly string $format, // roher Wert aus dem Formular, wird im Handler gegen ArticleAdImageFormat geprüft
        // ID eines vorhandenen ArticleImage, das als (einziges) Referenzfoto dienen soll ("images/edits");
        // null/leer = all gallery images when $useGalleryImages is set, otherwise
        // pure text-to-image generation ("images/generations").
        public readonly ?string $sourceArticleImageId,
        public readonly ?string $additionalInstructions,
        // Number of images to generate in one go (validated in the handler).
        public readonly int $count = 1,
        // Render the article's display name into the image as a text overlay.
        public readonly bool $includeArticleName = false,
        // Render key properties (manufacturer, weight, dimensions, ...) into the image.
        public readonly bool $includeProperties = false,
        // Render the daily rental price into the image.
        public readonly bool $includePrice = false,
        // Use the article's gallery photos as references so the product is depicted correctly.
        public readonly bool $useGalleryImages = false,
    ) {
    }
}
