<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Tour\Command\ApplyTourCrewChange;
use App\Application\Tour\Command\ApplyTourMaterialChange;
use App\Application\Tour\Command\CreateTourStopMinuteProgram;
use App\Application\Tour\Command\DeleteTourCrewDeviation;
use App\Application\Tour\Command\DeleteTourStop;
use App\Application\Tour\Command\DeleteTourStopItem;
use App\Application\Tour\Command\MakeTour;
use App\Application\Tour\Command\RevertTour;
use App\Application\Tour\Command\SaveTourStop;
use App\Application\Tour\Command\SaveTourWaypoint;
use App\Application\Tour\Query\GetTourOverview;
use App\Application\Tour\Query\TourOverview;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Tour\TourRepositoryInterface;
use App\Domain\Tour\TourStop;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use App\Infrastructure\Pdf\TourLoadListPdfGenerator;

/** Tour planning (migration 177): stops, waypoints, material and crew per stop, load lists. */
final class TourController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly TourRepositoryInterface $tours,
        private readonly ?TourLoadListPdfGenerator $pdf = null,
    ) {
    }

    public function make(Request $request): void
    {
        $rentalId = $request->param('id');
        $this->run(fn () => $this->commands->dispatch(new MakeTour($rentalId)), 'Die Veranstaltung wird jetzt als Tour geplant. Der bisherige Ort ist Stop 1.');
        Response::redirect('/veranstaltungen/' . $rentalId . '/tour');
    }

    public function revert(Request $request): void
    {
        $rentalId = $request->param('id');
        $ok = $this->run(fn () => $this->commands->dispatch(new RevertTour($rentalId)), 'Die Tour-Planung wurde entfernt.');
        Response::redirect('/veranstaltungen/' . $rentalId . ($ok ? '' : '/tour'));
    }

    public function show(Request $request): void
    {
        $overview = $this->overview($request->param('id'));
        if ($overview === null) {
            return;
        }
        $route = null;
        try {
            $route = $this->queries->ask(new \App\Application\Routing\Query\GetTourRoute($overview->rental->id));
        } catch (\Throwable) {
            // The tour page also works without route data.
        }
        echo View::page('rentals/tour/show', ['tour' => $overview, 'focusStop' => $request->query('stop'), 'route' => $route], activeNav: 'rentals', title: 'Tour – ' . $overview->rental->eventName);
    }

    public function stopForm(Request $request): void
    {
        $stopId = $request->param('stopId');
        $stop = $stopId !== '' ? $this->tours->findStop($stopId) : null;
        $rentalId = $stop?->rentalId ?? $request->param('id');
        $overview = $this->overview($rentalId);
        if ($overview === null) {
            return;
        }
        $endpoint = match ($request->query('art')) {
            'start' => 'start',
            'ziel' => 'end',
            default => null,
        };
        if ($stop === null && $endpoint !== null) {
            // Start/destination: edit the stored one, if any.
            foreach ($this->tours->findStopsByRental($rentalId) as $candidate) {
                if ($candidate->kind->value === $endpoint) {
                    $stop = $candidate;
                }
            }
        }
        $waypoint = $stop !== null ? !$stop->isStop() : ($request->query('art') === 'zwischenstopp' || $endpoint !== null);
        $old = $this->oldFromStop($stop, $request);
        $old['kind'] = $stop?->kind->value ?? ($endpoint ?? ($waypoint ? 'waypoint' : 'stop'));
        $this->renderStopForm($overview, $stop, $waypoint, [], $old);
    }

    public function saveStop(Request $request): void
    {
        $stopId = $request->param('stopId') !== '' ? $request->param('stopId') : null;
        $stop = $stopId !== null ? $this->tours->findStop($stopId) : null;
        if ($stopId !== null && $stop === null) {
            Response::notFound('Der Stop wurde nicht gefunden.');

            return;
        }
        $rentalId = $stop?->rentalId ?? $request->param('id');
        $in = static fn (string $key): string => trim($request->input($key, '') ?? '');
        $waypoint = $stop !== null ? !$stop->isStop() : in_array($in('kind'), ['waypoint', 'start', 'end'], true);
        try {
            if ($waypoint) {
                $this->commands->dispatch(new SaveTourWaypoint(
                    $rentalId,
                    $stopId,
                    $in('purpose'),
                    $in('address_type'),
                    $in('warehouse_id'),
                    $in('saved_address_id'),
                    $in('label'),
                    $in('street'),
                    $in('postal_code'),
                    $in('city'),
                    $in('country'),
                    $in('after_stop_id'),
                    $in('arrive_at'),
                    $in('depart_at'),
                    $in('note'),
                    $in('remember_address') === '1',
                    $stop?->kind->value ?? (in_array($in('kind'), ['start', 'end'], true) ? $in('kind') : 'waypoint'),
                ));
            } else {
                $this->commands->dispatch(new SaveTourStop(
                    $rentalId,
                    $stopId,
                    $in('location'),
                    $in('setup_at'),
                    $in('event_start_at'),
                    $in('event_end_at'),
                    $in('teardown_end_at'),
                    $in('contact_name'),
                    $in('contact_phone'),
                    $in('note'),
                ));
            }
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        } catch (ValidationException $e) {
            $overview = $this->overview($rentalId);
            if ($overview !== null) {
                $this->renderStopForm($overview, $stop, $waypoint, $e->errors(), is_array($request->post) ? array_map(static fn ($v) => is_scalar($v) ? (string) $v : '', $request->post) : []);
            }

            return;
        }
        $kind = $stop?->kind->value ?? $in('kind');
        Flash::success(match ($kind) {
            'start' => 'Start der Route gespeichert.',
            'end' => 'Ziel der Route gespeichert.',
            default => $waypoint ? 'Zwischenstopp gespeichert.' : 'Stop gespeichert.',
        });
        Response::redirect('/veranstaltungen/' . $rentalId . '/tour' . (in_array($kind, ['start', 'end'], true) ? '/route' : ''));
    }

    public function deleteStop(Request $request): void
    {
        $stop = $this->tours->findStop($request->param('stopId'));
        if ($stop === null) {
            Response::notFound('Der Stop wurde nicht gefunden.');

            return;
        }
        $this->run(fn () => $this->commands->dispatch(new DeleteTourStop($stop->id)), $stop->isStop() ? 'Stop gelöscht.' : 'Zwischenstopp gelöscht.');
        Response::redirect('/veranstaltungen/' . $stop->rentalId . '/tour');
    }

    public function applyMaterial(Request $request): void
    {
        $rentalId = $request->param('id');
        $this->run(function () use ($request, $rentalId): string {
            $count = (int) $this->commands->dispatch(new ApplyTourMaterialChange(
                $rentalId,
                $request->inputArray('stops'),
                $request->input('article_id', '') ?? '',
                $request->input('quantity', '') ?? '',
                $request->input('allocation', '') ?? '',
                $request->input('issue_waypoint_id', '') ?? '',
                $request->input('return_waypoint_id', '') ?? '',
            ));

            return $count === 1 ? 'Material an 1 Stop geändert.' : "Material an {$count} Stops geändert.";
        });
        Response::redirect('/veranstaltungen/' . $rentalId . '/tour#material');
    }

    public function deleteItem(Request $request): void
    {
        $rentalId = $request->param('id');
        $this->run(fn () => $this->commands->dispatch(new DeleteTourStopItem($rentalId, $request->param('itemId'))), 'Abweichung entfernt.');
        Response::redirect('/veranstaltungen/' . $rentalId . '/tour');
    }

    public function applyCrew(Request $request): void
    {
        $rentalId = $request->param('id');
        $this->run(function () use ($request, $rentalId): string {
            $result = $this->commands->dispatch(new ApplyTourCrewChange(
                $rentalId,
                $request->inputArray('stops'),
                $request->input('action', '') ?? '',
                $request->input('user_id', '') ?? '',
                $request->input('replaces_user_id', '') ?? '',
            ));

            return "Besetzung an {$result['changed']} Stop(s) geändert." . ($result['requested'] ? ' Der Techniker wurde angefragt.' : '');
        });
        Response::redirect('/veranstaltungen/' . $rentalId . '/tour#crew');
    }

    public function deleteCrewDeviation(Request $request): void
    {
        $rentalId = $request->param('id');
        $this->run(fn () => $this->commands->dispatch(new DeleteTourCrewDeviation($rentalId, $request->param('deviationId'))), 'Abweichung der Besetzung entfernt.');
        Response::redirect('/veranstaltungen/' . $rentalId . '/tour');
    }

    public function createMinuteProgram(Request $request): void
    {
        $stop = $this->tours->findStop($request->param('stopId'));
        if ($stop === null) {
            Response::notFound('Der Stop wurde nicht gefunden.');

            return;
        }
        try {
            $programId = (string) $this->commands->dispatch(new CreateTourStopMinuteProgram($stop->id));
            Flash::success('Minutenprogramm für den Stop angelegt.');
            Response::redirect('/minutenprogramme/' . $programId);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/veranstaltungen/' . $stop->rentalId . '/tour');
        }
    }

    public function loadList(Request $request): void
    {
        $overview = $this->overview($request->param('id'));
        if ($overview === null) {
            return;
        }
        echo View::page('rentals/tour/load-list', ['tour' => $overview], activeNav: 'rentals', title: 'Ladelisten – ' . $overview->rental->eventName);
    }

    public function loadListPdf(Request $request): void
    {
        $overview = $this->overview($request->param('id'));
        if ($overview === null || $this->pdf === null) {
            return;
        }
        Response::inlinePdf($this->pdf->generate($overview), 'Ladelisten ' . preg_replace('/[^\w\- ]+/u', '', $overview->rental->eventName) . '.pdf');
    }

    // --- helpers -------------------------------------------------------

    private function overview(string $rentalId): ?TourOverview
    {
        try {
            return $this->queries->ask(new GetTourOverview($rentalId));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/veranstaltungen/' . $rentalId);
        }

        return null;
    }

    /** Runs a command, flashes the result; returns whether it succeeded. */
    private function run(callable $work, ?string $success = null): bool
    {
        try {
            $result = $work();
            Flash::success(is_string($result) ? $result : (string) $success);

            return true;
        } catch (ValidationException $e) {
            Flash::error(implode(' ', array_unique($e->errors())));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        return false;
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $old
     */
    private function renderStopForm(TourOverview $overview, ?TourStop $stop, bool $waypoint, array $errors, array $old): void
    {
        echo View::page('rentals/tour/stop-form', [
            'tour' => $overview,
            'stop' => $stop,
            'waypoint' => $waypoint,
            'errors' => $errors,
            'old' => $old,
        ], activeNav: 'rentals', title: ($waypoint ? 'Zwischenstopp' : 'Stop') . ' – ' . $overview->rental->eventName);
    }

    /** @return array<string, string> */
    private function oldFromStop(?TourStop $stop, Request $request): array
    {
        $dt = static fn (?\DateTimeImmutable $d): string => $d?->format('Y-m-d\TH:i') ?? '';
        if ($stop === null) {
            return ['after_stop_id' => $request->query('nach') ?? '', 'purpose' => 'load', 'address_type' => 'warehouse'];
        }

        return [
            'location' => (string) $stop->locationText,
            'setup_at' => $dt($stop->setupAt),
            'event_start_at' => $dt($stop->eventStartAt),
            'event_end_at' => $dt($stop->eventEndAt),
            'teardown_end_at' => $dt($stop->teardownEndAt),
            'contact_name' => (string) $stop->contactName,
            'contact_phone' => (string) $stop->contactPhone,
            'note' => (string) $stop->note,
            'purpose' => $stop->purpose?->value ?? 'load',
            'address_type' => $stop->addressType?->value ?? 'custom',
            'warehouse_id' => (string) $stop->warehouseId,
            'saved_address_id' => (string) $stop->savedAddressId,
            'label' => (string) $stop->label,
            'street' => (string) $stop->street,
            'postal_code' => (string) $stop->postalCode,
            'city' => (string) $stop->city,
            'country' => (string) $stop->country,
            'after_stop_id' => (string) $stop->afterStopId,
            'arrive_at' => $dt($stop->arriveAt),
            'depart_at' => $dt($stop->departAt),
        ];
    }
}
