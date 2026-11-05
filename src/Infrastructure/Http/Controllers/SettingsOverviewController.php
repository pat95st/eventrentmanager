<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\SettingsSections;
use App\Infrastructure\Http\View;

/** Übersicht aller Einstellungen mit Suche (Nutzerwunsch: Einstellungen leichter finden). */
final class SettingsOverviewController
{
    public function index(Request $request): void
    {
        echo View::page('settings/overview', [
            'groups' => SettingsSections::groups(),
        ], activeNav: 'settings-overview', title: 'Einstellungen');
    }
}
