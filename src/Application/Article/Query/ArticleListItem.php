<?php

declare(strict_types=1);

namespace App\Application\Article\Query;

use App\Domain\Article\Article;
use App\Domain\Category\Category;
use App\Domain\Manufacturer\Manufacturer;

/**
 * Ein Artikel mit bereits aufgelösten Fremdschlüsseln (Hersteller/Kategorie)
 * — das gemeinsame Lesemodell für die Artikelübersicht. Wird ausschließlich
 * vom Use Case ListArticlesForDisplay erzeugt und identisch vom
 * HTML-Frontend (ArticleController::index) und von der REST-API
 * (ArticleApiController::index) konsumiert, damit beide Kanäle exakt
 * dieselben Daten ohne doppelte Auflösungslogik erhalten.
 */
final class ArticleListItem
{
    public function __construct(
        public readonly Article $article,
        // Nullbar statt exception-werfend (anders als GetArticleHandler),
        // damit die Übersicht bei einem inkonsistenten Datensatz nicht
        // komplett scheitert — die Ansicht zeigt dann "–" statt eines Namens.
        public readonly ?Manufacturer $manufacturer,
        public readonly ?Category $category,
    ) {
    }
}
