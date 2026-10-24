<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Firmware\Query\ListFirmwareUpdatesAvailable;
use App\Application\Query\QueryBus;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\View;

final class FirmwareController
{
    public function __construct(private readonly QueryBus $queries)
    {
    }

    /** Artikelübergreifende Übersicht aller Inventurnummern mit verfügbarem Firmware-Update, erreichbar über das Hauptmenü. */
    public function overview(Request $request): void
    {
        $search = $request->query('q');

        $entries = $this->queries->ask(new ListFirmwareUpdatesAvailable($search));

        echo View::page('firmware/overview', [
            'entries' => $entries,
            'search' => $search ?? '',
        ], activeNav: 'firmware', title: 'Firmware-Updates verfügbar');
    }
}
