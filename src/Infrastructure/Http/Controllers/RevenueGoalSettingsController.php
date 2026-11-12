<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\RevenueGoal\DeleteRevenueGoal;
use App\Application\RevenueGoal\ListRevenueGoals;
use App\Application\RevenueGoal\SaveRevenueGoals;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\t;

/**
 * Einstellungsseite der Umsatzziele (Migration 146): Ziele mit einem für
 * jedes Jahr geltenden Standardbetrag, der sich für das oben gewählte Jahr
 * einzeln überschreiben lässt. Der Stand je Ziel steht im Bericht
 * Einnahmen/Ausgaben.
 */
final class RevenueGoalSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render($this->year($request), [], []);
    }

    public function update(Request $request): void
    {
        $year = $this->year($request);
        $rows = is_array($request->post['goals'] ?? null) ? $request->post['goals'] : [];
        $goals = [];
        foreach ($rows as $id => $row) {
            if (is_array($row)) {
                $goals[(string) $id] = self::row($row);
            }
        }
        $new = self::row(is_array($request->post['new'] ?? null) ? $request->post['new'] : []);

        try {
            $this->commands->dispatch(new SaveRevenueGoals($year, $goals, $new));
            Flash::success(t('settings.revenue_goals.flash.saved'));
            Response::redirect('/einstellungen/umsatzziele?jahr=' . $year);
        } catch (ValidationException $e) {
            $this->render($year, $e->errors(), ['goals' => $goals, 'new' => $new]);
        }
    }

    public function delete(Request $request): void
    {
        $this->commands->dispatch(new DeleteRevenueGoal($request->param('id')));
        Flash::success(t('settings.revenue_goals.flash.deleted'));
        Response::redirect('/einstellungen/umsatzziele?jahr=' . $this->year($request));
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function render(int $year, array $errors, array $old): void
    {
        echo View::page('settings/revenue-goals', [
            'goals' => $this->queries->ask(new ListRevenueGoals()),
            'year' => $year,
            'currentYear' => (int) date('Y'),
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'settings-umsatzziele', title: t('settings.revenue_goals.title'));
    }

    private function year(Request $request): int
    {
        $currentYear = (int) date('Y');
        $input = (string) $request->input('jahr', '');
        $year = ctype_digit($input) ? (int) $input : $currentYear;

        return $year < 2000 || $year > $currentYear + 10 ? $currentYear : $year;
    }

    /**
     * @param array<mixed> $row
     * @return array{name: string, kind: string, amount: string, year_amount: string}
     */
    private static function row(array $row): array
    {
        $value = static fn (string $key): string => is_scalar($row[$key] ?? null) ? (string) $row[$key] : '';

        return ['name' => $value('name'), 'kind' => $value('kind'), 'amount' => $value('amount'), 'year_amount' => $value('year_amount')];
    }
}
