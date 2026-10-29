<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandInterface;

/**
 * Wählt ein Galeriebild des Artikels als Dokumentbild (Migration 109) —
 * es erscheint klein links neben dem Artikelnamen auf Angebot, AB und
 * Rechnung. imageId = null entfernt die Auswahl.
 */
final class SetArticleDocumentImage implements CommandInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly ?string $imageId,
    ) {
    }
}
