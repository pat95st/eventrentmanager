<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandInterface;

final class AddArticleAccessory implements CommandInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly string $quantity, // als String aus dem Formular, wird im Handler geprüft/konvertiert
        public readonly string $description,
    ) {
    }
}
