<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Query;

use App\Application\Query\QueryInterface;

final class ListVehicles implements QueryInterface
{
    /**
     * $page/$pageSize bleiben absichtlich nullbar: ohne Angabe (z. B. für
     * die Fahrzeug-Dropdown-Auswahl in der Beladungsliste) liefert der
     * Handler ALLE Treffer ohne LIMIT, aber weiterhin als PaginatedResult
     * (siehe ListSuppliers für das gleiche Muster).
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $page = null,
        public readonly ?int $pageSize = null,
    ) {
    }
}
