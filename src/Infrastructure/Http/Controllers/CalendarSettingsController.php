<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Calendar\Command\CreateCalendar;
use App\Application\Calendar\Command\DeleteCalendar;
use App\Application\Calendar\Command\SetCalendarAccess;
use App\Application\Calendar\Command\SetCalendarEnabled;
use App\Application\Calendar\Command\UpdateCalendar;
use App\Application\Calendar\Query\ListCalendars;
use App\Application\Calendar\Query\ListUserIdsForCalendar;
use App\Application\Category\Query\ListCategories;
use App\Application\Command\CommandBus;
use App\Application\Manufacturer\Query\ListManufacturers;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\DeleteCalDavSigningCertificate;
use App\Application\Settings\Command\UploadCalDavSigningCertificate;
use App\Application\Settings\Query\GetCalDavSigningSettings;
use App\Application\User\Query\ListUsers;
use App\Application\Venue\Query\ListVenues;
use App\Application\Warehouse\Query\ListWarehouses;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class CalendarSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render([], $request->query('bearbeiten'), $request->query('zugriff'));
    }

    public function createCalendar(Request $request): void
    {
        try {
            $this->commands->dispatch(new CreateCalendar(
                name: $request->input('name'),
                categoryId: $request->input('category_id'),
                manufacturerId: $request->input('manufacturer_id'),
                warehouseId: $request->input('warehouse_id'),
                venueId: $request->input('venue_id'),
            ));
        } catch (ValidationException $e) {
            $this->render($e->errors(), null, null);

            return;
        }

        Flash::success('Kalender wurde angelegt.');
        Response::redirect('/einstellungen/kalender');
    }

    public function updateCalendar(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new UpdateCalendar(
                id: $id,
                name: $request->input('name'),
                categoryId: $request->input('category_id'),
                manufacturerId: $request->input('manufacturer_id'),
                warehouseId: $request->input('warehouse_id'),
                venueId: $request->input('venue_id'),
            ));
            Flash::success('Kalender wurde aktualisiert.');
            Response::redirect('/einstellungen/kalender');

            return;
        } catch (ValidationException $e) {
            $this->render($e->errors(), $id, null);

            return;
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/kalender');
    }

    public function enableCalendar(Request $request): void
    {
        $this->setEnabled($request, true);
    }

    public function disableCalendar(Request $request): void
    {
        $this->setEnabled($request, false);
    }

    private function setEnabled(Request $request, bool $enabled): void
    {
        try {
            $this->commands->dispatch(new SetCalendarEnabled($request->param('id'), $enabled));
            Flash::success($enabled ? 'Kalender wurde aktiviert.' : 'Kalender wurde deaktiviert.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/kalender');
    }

    public function deleteCalendar(Request $request): void
    {
        try {
            $this->commands->dispatch(new DeleteCalendar($request->param('id')));
            Flash::success('Kalender wurde gelöscht.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/kalender');
    }

    public function updateAccess(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new SetCalendarAccess($id, $request->inputArray('user_ids')));
            Flash::success('Zugriffsrechte wurden gespeichert.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/kalender');
    }

    public function uploadSigningCertificate(Request $request): void
    {
        try {
            $this->commands->dispatch(new UploadCalDavSigningCertificate(
                certificateFile: $request->file('certificate'),
                privateKeyFile: $request->file('private_key'),
            ));
            Flash::success('Signatur-Zertifikat wurde gespeichert.');
            Response::redirect('/einstellungen/kalender');

            return;
        } catch (ValidationException $e) {
            $this->render([], null, null, $e->errors());
        }
    }

    public function deleteSigningCertificate(Request $request): void
    {
        $this->commands->dispatch(new DeleteCalDavSigningCertificate());
        Flash::success('Signatur-Zertifikat wurde entfernt, .mobileconfig-Profile werden ab sofort wieder selbstsigniert.');
        Response::redirect('/einstellungen/kalender');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $signingErrors
     */
    private function render(array $errors, ?string $editCalendarId, ?string $accessCalendarId, array $signingErrors = []): void
    {
        $editCalendarId = $editCalendarId !== '' ? $editCalendarId : null;
        $accessCalendarId = $accessCalendarId !== '' ? $accessCalendarId : null;

        echo View::page('settings/calendars', [
            'calendars' => $this->queries->ask(new ListCalendars()),
            'categories' => $this->queries->ask(new ListCategories()),
            'manufacturers' => $this->queries->ask(new ListManufacturers()),
            'warehouses' => $this->queries->ask(new ListWarehouses())->items,
            'venues' => $this->queries->ask(new ListVenues(page: 1, pageSize: 10000))->items,
            'users' => $this->queries->ask(new ListUsers(1, 10000))->items,
            'errors' => $errors,
            'editCalendarId' => $editCalendarId,
            'accessCalendarId' => $accessCalendarId,
            'accessUserIds' => $accessCalendarId !== null
                ? $this->queries->ask(new ListUserIdsForCalendar($accessCalendarId))
                : [],
            'signingSettings' => $this->queries->ask(new GetCalDavSigningSettings()),
            'signingErrors' => $signingErrors,
        ], activeNav: 'settings-kalender', title: t('settings.calendars.title'));
    }
}
