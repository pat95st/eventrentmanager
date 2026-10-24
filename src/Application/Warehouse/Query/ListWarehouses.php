<?php

declare(strict_types=1);

namespace App\Application\Warehouse\Query;

use App\Application\Query\QueryInterface;

final class ListWarehouses implements QueryInterface
{
    /** $page/$pageSize bleiben absichtlich nullbar: ohne Angabe (Lagerort-Auswahl im Veranstaltungsformular) liefert der Handler ALLE Treffer ohne LIMIT. */
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?int $page = null,
        public readonly ?int $pageSize = null,
    ) {
    }
}
