<?php

declare(strict_types=1);

namespace App\Application\Customer\Query;

use App\Application\Query\QueryInterface;

final class ListCustomers implements QueryInterface
{
    /**
     * $page/$pageSize bleiben absichtlich nullbar: ohne Angabe (z. B. für
     * die Kunden-Dropdown-Auswahl auf der Veranstaltungsseite) liefert der
     * Handler ALLE Treffer ohne LIMIT, aber weiterhin als PaginatedResult
     * (siehe ListCustomersHandler) — für die eigentliche Kundenübersicht
     * (CustomerController::index) werden beide Werte gesetzt.
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $page = null,
        public readonly ?int $pageSize = null,
    ) {
    }
}
