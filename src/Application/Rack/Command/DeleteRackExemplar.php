<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandInterface;

/** Rack-Exemplar samt Bestückung löschen; liefert die ID des Rack-Typs. */
final class DeleteRackExemplar implements CommandInterface
{
    public function __construct(public readonly string $exemplarId)
    {
    }
}
