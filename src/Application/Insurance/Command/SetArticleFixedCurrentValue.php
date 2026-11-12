<?php

declare(strict_types=1);

namespace App\Application\Insurance\Command;

use App\Application\Command\CommandInterface;

/** Festen Zeitwert eines Artikels setzen (gilt für alle Exemplare ohne eigenen Wert); leer/null löscht ihn. */
final class SetArticleFixedCurrentValue implements CommandInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly ?string $value,
    ) {
    }
}
