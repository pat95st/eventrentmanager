<?php

declare(strict_types=1);

namespace App\Application\Article\Query;

use App\Application\Query\QueryInterface;

final class ListArticles implements QueryInterface
{
    /**
     * $page/$pageSize bleiben absichtlich nullbar: ohne Angabe (z. B. für
     * die Artikel-Auswahl beim Anlegen einer Veranstaltungsposition oder
     * eines Case-Inhalts) liefert der Handler ALLE Treffer ohne LIMIT,
     * aber weiterhin als PaginatedResult (siehe ListArticlesHandler) — für
     * die eigentliche Artikelübersicht (ArticleController::index) werden
     * beide Werte gesetzt.
     */
    public function __construct(
        public readonly ?string $searchTerm = null,
        public readonly ?string $manufacturerId = null,
        public readonly ?string $categoryId = null,
        public readonly ?int $page = null,
        public readonly ?int $pageSize = null,
        // true bei der Artikelauswahl (Angebote/Rechnungen/Veranstaltungs-
        // positionen/Case-Inhalte): deaktivierte Artikel werden ausgeblendet.
        // false (Standard) in der Artikelverwaltung selbst, wo auch
        // deaktivierte Artikel sichtbar/bearbeitbar bleiben müssen.
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
