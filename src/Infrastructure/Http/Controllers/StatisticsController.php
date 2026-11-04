<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Query\QueryBus;
use App\Application\Statistics\Query\GetStatistic;
use App\Application\Statistics\StatisticKey;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Detailseite einer Dashboard-Statistik (/statistiken/{key}). */
final class StatisticsController
{
    /** Wählbare Zeiträume in Monaten. */
    public const PERIODS = [6, 12, 24];

    public function __construct(private readonly QueryBus $queries)
    {
    }

    public function show(Request $request): void
    {
        $key = StatisticKey::tryFrom((string) $request->param('key'));
        $user = CurrentUser::get();
        if ($key === null || $user === null || !$user->has($key->requiredPermission())) {
            Response::notFound('Diese Statistik gibt es nicht.');

            return;
        }

        $months = (int) ($request->query('monate') ?? 12);
        if (!in_array($months, self::PERIODS, true)) {
            $months = 12;
        }

        $chart = $this->queries->ask(new GetStatistic($key, $months, detailed: true));

        echo View::page('statistics/show', [
            'chart' => $chart,
            'months' => $months,
            'periods' => self::PERIODS,
            'others' => array_values(array_filter(StatisticKey::cases(), static fn (StatisticKey $k) => $k !== $key && $user->has($k->requiredPermission()))),
        ], activeNav: 'dashboard', title: $key->label());
    }
}
