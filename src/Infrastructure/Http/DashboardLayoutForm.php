<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Dashboard\DashboardLayout;

/**
 * Liest die Felder des Anordnungs-Editors (resources/views/dashboard/
 * _layout_editor.php): `order[]` nennt die Kacheln in der Reihenfolge, in
 * der sie im Formular stehen, `zone[<Schlüssel>]` den Bereich je Kachel
 * ("hidden" oder unbekannt = ausgeblendet). So funktioniert das Formular
 * auch ohne JavaScript.
 */
final class DashboardLayoutForm
{
    /** @return array<string, list<string>> Bereich => Kachel-Schlüssel, ungeprüft */
    public static function zones(Request $request): array
    {
        $zoneOf = is_array($request->post['zone'] ?? null) ? $request->post['zone'] : [];

        $zones = [];
        foreach ($request->inputArray('order') as $key) {
            $zone = $zoneOf[$key] ?? null;
            if (is_string($zone) && in_array($zone, DashboardLayout::ZONES, true)) {
                $zones[$zone][] = $key;
            }
        }

        return $zones;
    }
}
