<?php

declare(strict_types=1);

namespace App\Application\Article\Query;

use App\Application\Query\QueryInterface;

/**
 * Zentraler Use Case für "Artikelliste mit aufgelöstem Hersteller/Kategorie".
 * Nimmt dieselben Filterparameter wie ListArticles entgegen (an das intern
 * delegiert wird, siehe ListArticlesForDisplayHandler) und liefert
 * zusätzlich die aufgelösten Fremdschlüssel mit — genau die Form, die sowohl
 * die HTML-Artikelübersicht (ArticleController::index) als auch die
 * REST-API (ArticleApiController::index) benötigen. Beide Kanäle nutzen
 * diesen einen Use Case als zentrale Anlaufstelle, statt jeweils eigene
 * Hersteller-/Kategorie-Auflösung zu implementieren.
 */
final class ListArticlesForDisplay implements QueryInterface
{
    public function __construct(
        public readonly ?string $searchTerm = null,
        public readonly ?string $manufacturerId = null,
        public readonly ?string $categoryId = null,
        public readonly ?int $page = null,
        public readonly ?int $pageSize = null,
        public readonly bool $onlyActive = false,
        // Mehrfachfilter (Nutzerwunsch: Artikel-Auswahl-Modal mit mehreren
        // Herstellern/Kategorien gleichzeitig) — zusätzlich zu den
        // Einzelfiltern oben (die die Artikelübersicht weiter nutzt).
        // Innerhalb einer Liste ODER-verknüpft, zwischen den Listen UND.
        /** @var string[] */
        public readonly array $manufacturerIds = [],
        /** @var string[] */
        public readonly array $categoryIds = [],
        // Bundles (Migration 107): Artikelauswahl für Vermietungen/Miet-
        // Positionen blendet nicht einzeln vermietbare Artikel aus;
        // Auswahl für physische Zwecke (Einkauf, Cases, Verkauf) blendet
        // Bundles aus.
        public readonly bool $onlyRentableIndividually = false,
        public readonly bool $excludeBundles = false,
    ) {
    }
}
