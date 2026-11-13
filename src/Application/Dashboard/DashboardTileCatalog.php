<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Statistics\StatisticKey;
use App\Domain\Dashboard\DashboardLayout;
use App\Domain\Dashboard\DashboardWidget;
use App\Domain\User\User;

/**
 * Alle Kacheln, die es für die Startseite gibt, und die einzige Stelle, die
 * entscheidet, welche davon ein Benutzer sehen darf: Jede Kachel verlangt
 * ein Recht (DashboardTile::$requiredPermission). Ohne dieses Recht taucht
 * sie weder auf der Startseite noch in der Auswahl unter /profil/dashboard
 * noch in der Vorschau einer Vorlage auf – egal, was gespeichert ist.
 */
final class DashboardTileCatalog
{
    /** Präfix der Kachel-Schlüssel für Statistik-Diagramme, z. B. "stat:einnahmen". */
    private const string STATISTIC_PREFIX = 'stat:';

    /** @return array<string, DashboardTile> alle Kacheln in Standardreihenfolge, nach Schlüssel */
    public static function all(): array
    {
        $tiles = [];
        foreach (DashboardWidget::all() as $widget) {
            $tiles[$widget->value] = new DashboardTile(
                $widget->value,
                $widget->label(),
                $widget->description(),
                $widget->requiredPermission(),
                $widget === DashboardWidget::UpcomingRentals ? DashboardTile::KIND_SCHEDULE : DashboardTile::KIND_COMPACT,
                widget: $widget,
            );
        }
        foreach (StatisticKey::cases() as $statistic) {
            $key = self::STATISTIC_PREFIX . $statistic->value;
            $tiles[$key] = new DashboardTile(
                $key,
                $statistic->label(),
                $statistic->description(),
                $statistic->requiredPermission(),
                DashboardTile::KIND_CHART,
                statistic: $statistic,
            );
        }

        return $tiles;
    }

    /** @return array<string, DashboardTile> die Kacheln, für die der Benutzer das nötige Recht hat */
    public static function permittedFor(User $user): array
    {
        return array_filter(self::all(), static fn (DashboardTile $tile) => $user->has($tile->requiredPermission));
    }

    /**
     * Die Anordnung, die der Benutzer tatsächlich sieht: seine eigene (sonst
     * die Standardanordnung), immer beschränkt auf die erlaubten Kacheln.
     */
    public static function layoutFor(User $user): DashboardLayout
    {
        $layout = $user->dashboardLayout ?? self::defaultLayout($user->dashboardWidgets);

        return $layout->only(array_keys(self::permittedFor($user)));
    }

    /**
     * Standardanordnung: Lagerplan links, Zähler rechts, Diagramme darunter.
     *
     * @param string[]|null $enabledWidgets frühere Auswahl aus users.dashboard_widgets
     *        (Migration 090); NULL = alle Bereiche
     */
    public static function defaultLayout(?array $enabledWidgets = null): DashboardLayout
    {
        $zones = [DashboardLayout::ZONE_MAIN => [], DashboardLayout::ZONE_SIDE => [], DashboardLayout::ZONE_BOTTOM => []];
        foreach (self::all() as $tile) {
            if ($tile->widget !== null && $enabledWidgets !== null && !in_array($tile->key, $enabledWidgets, true)) {
                continue;
            }
            $zone = match ($tile->kind) {
                DashboardTile::KIND_SCHEDULE => DashboardLayout::ZONE_MAIN,
                DashboardTile::KIND_CHART => DashboardLayout::ZONE_BOTTOM,
                default => DashboardLayout::ZONE_SIDE,
            };
            $zones[$zone][] = $tile->key;
        }

        return DashboardLayout::fromZones($zones);
    }
}
