<?php

declare(strict_types=1);

namespace App\Application\Supplier\Query;

use App\Application\Query\QueryInterface;

final class ListSuppliers implements QueryInterface
{
    /**
     * $page/$pageSize bleiben absichtlich nullbar: ohne Angabe (z. B. für
     * die Lieferanten-Dropdown-Auswahl beim Anlegen einer Inventurnummer)
     * liefert der Handler ALLE Treffer ohne LIMIT, aber weiterhin als
     * PaginatedResult (siehe ListSuppliersHandler) — für die eigentliche
     * Lieferantenübersicht (SupplierController::index) werden beide Werte
     * gesetzt.
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $page = null,
        public readonly ?int $pageSize = null,
    ) {
    }
}
