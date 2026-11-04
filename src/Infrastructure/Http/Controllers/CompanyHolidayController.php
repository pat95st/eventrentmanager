<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\CompanyHoliday\Command\DeleteCompanyHoliday;
use App\Application\CompanyHoliday\Command\SaveCompanyHoliday;
use App\Application\CompanyHoliday\Query\ListCompanyHolidays;
use App\Application\Query\QueryBus;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/** Einstellungen › Firma › Betriebsurlaub (Migration 130). */
final class CompanyHolidayController
{
    private const URL = '/einstellungen/betriebsurlaub';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        echo View::page('settings/company-holidays', [
            'holidays' => $this->queries->ask(new ListCompanyHolidays()),
        ], activeNav: 'settings-betriebsurlaub', title: 'Betriebsurlaub');
    }

    public function store(Request $request): void
    {
        $this->save(null, $request, 'Der Betriebsurlaub wurde angelegt.');
    }

    public function update(Request $request): void
    {
        $this->save($request->param('id'), $request, 'Der Betriebsurlaub wurde gespeichert.');
    }

    public function destroy(Request $request): void
    {
        $this->commands->dispatch(new DeleteCompanyHoliday($request->param('id')));
        Flash::success('Der Betriebsurlaub wurde gelöscht.');
        Response::redirect(self::URL);
    }

    private function save(?string $id, Request $request, string $success): void
    {
        try {
            $this->commands->dispatch(new SaveCompanyHoliday(
                id: $id,
                startDate: $request->input('start_date', '') ?? '',
                endDate: $request->input('end_date', '') ?? '',
                note: $request->input('note'),
            ));
            Flash::success($success);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect(self::URL);
    }
}
