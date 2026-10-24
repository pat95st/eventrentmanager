<?php

declare(strict_types=1);

namespace App\Application\Firmware\Query;

use App\Application\Query\QueryInterface;

final class ListFirmwareUpdatesAvailable implements QueryInterface
{
    public function __construct(public readonly ?string $searchTerm = null) // durchsucht Artikelname, Hersteller, Inventurnummer
    {
    }
}
