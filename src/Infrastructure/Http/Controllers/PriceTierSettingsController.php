<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdatePriceTiers;
use App\Application\Settings\Query\GetPriceTiers;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PriceTier;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class PriceTierSettingsController
{
    private const DEFAULT_DAYS = 7;
    private const MIN_DAYS = 2;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $tiers = $this->queries->ask(new GetPriceTiers());

        // "tage" erlaubt es, die Anzahl der angezeigten Staffel-Tage VOR dem
        // eigentlichen Speichern anzupassen (mehr/weniger Zeilen einblenden),
        // ohne dass dabei schon etwas in der Datenbank verändert wird —
        // gespeichert wird erst über den "Speichern"-Button (update()).
        $maxDays = $this->clampMaxDays((int) ($request->query('tage') ?? $this->currentMaxDays($tiers)));

        echo View::page('settings/price-tiers', [
            'tiers' => $tiers,
            'maxDays' => $maxDays,
            'errors' => [],
            'old' => [],
        ], activeNav: 'settings-preisstaffelung', title: t('settings.price_tiers.title'));
    }

    public function update(Request $request): void
    {
        $maxDays = $this->clampMaxDays((int) ($request->input('max_days', (string) self::DEFAULT_DAYS) ?? self::DEFAULT_DAYS));

        $percentages = [];
        $old = ['max_days' => (string) $maxDays];
        for ($day = self::MIN_DAYS; $day <= $maxDays; $day++) {
            $value = $request->input("percentage_{$day}", '') ?? '';
            $percentages[$day] = $value;
            $old["percentage_{$day}"] = $value;
        }

        try {
            $this->commands->dispatch(new UpdatePriceTiers($maxDays, $percentages));
            Flash::success(t('settings.price_tiers.flash.saved'));
            Response::redirect('/einstellungen/preisstaffelung');
        } catch (ValidationException $e) {
            $tiers = $this->queries->ask(new GetPriceTiers());
            echo View::page('settings/price-tiers', [
                'tiers' => $tiers,
                'maxDays' => $maxDays,
                'errors' => $e->errors(),
                'old' => $old,
            ], activeNav: 'settings-preisstaffelung', title: t('settings.price_tiers.title'));
        }
    }

    /** @param PriceTier[] $tiers */
    private function currentMaxDays(array $tiers): int
    {
        $days = array_map(static fn (PriceTier $tier) => $tier->dayCount, $tiers);

        return $days === [] ? self::DEFAULT_DAYS : max($days);
    }

    private function clampMaxDays(int $maxDays): int
    {
        return max(self::MIN_DAYS, min(PriceTier::MAX_DAY_COUNT, $maxDays));
    }
}
