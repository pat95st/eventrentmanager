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
    ) {
    }
}
