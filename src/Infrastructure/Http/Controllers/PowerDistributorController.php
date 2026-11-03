<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/**
 * Stromverteiler (Migration 117) werden direkt im Artikelformular gepflegt
 * (Nutzerwunsch). Die frühere eigene Seite leitet dorthin weiter, damit alte
 * Links weiter funktionieren.
 */
final class PowerDistributorController
{
    public function edit(Request $request): void
    {
        Response::redirect('/artikel/' . rawurlencode($request->param('id')) . '/bearbeiten#power_distributor_fields');
    }
}
