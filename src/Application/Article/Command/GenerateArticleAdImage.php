<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandInterface;

final class GenerateArticleAdImage implements CommandInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly string $format, // roher Wert aus dem Formular, wird im Handler gegen ArticleAdImageFormat geprüft
        // ID eines vorhandenen ArticleImage, das als Referenzfoto dienen soll ("images/edits");
        // null/leer = reine Text-zu-Bild-Generierung ("images/generations").
        public readonly ?string $sourceArticleImageId,
        public readonly ?string $additionalInstructions,
    ) {
    }
}
