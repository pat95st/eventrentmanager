<?php

declare(strict_types=1);

namespace App\Application\Rack\Query;

use App\Application\Query\QueryInterface;

/** Rack-Typ eines Exemplars (für Beschriftungs-PDFs und Exemplar-Aktionen). */
final class GetRackByExemplar implements QueryInterface
{
    public function __construct(public readonly string $exemplarId)
    {
    }
}
