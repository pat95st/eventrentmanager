<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Federation\Command\AcceptIncomingPartnerRequest;
use App\Application\Federation\Command\CancelOutgoingPartnerRequest;
use App\Application\Federation\Command\DeclineIncomingPartnerRequest;
use App\Application\Federation\Command\RefreshOutgoingPartnerRequest;
use App\Application\Federation\Command\ResendPartnerRequestStatus;
use App\Application\Federation\Command\SendPartnerMaterialRequest;
use App\Application\Federation\Command\SendPartnerTechnicianRequest;
use App\Application\Federation\Query\BrowsePartnerPool;
use App\Application\Federation\Query\GetPartnerOverview;
use App\Application\Federation\Query\GetPartnerRequest;
use App\Application\Federation\Query\GetPartnerTechnicianOptions;
use App\Application\Federation\Query\ListPartnerRequests;
use App\Application\Query\QueryBus;
use App\Application\Rental\Query\GetRental;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Partners › material pool, technician requests and the request inbox
 * (migration 157). Available to everyone who may plan events.
 */
final class PartnerController
{
    private const NAV = 'partners';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $overview = $this->queries->ask(new GetPartnerOverview());
        echo View::page('partners/index', [
            'partners' => array_values(array_filter($overview->partners, static fn ($p) => $p->isActive())),
            'requests' => $this->queries->ask(new ListPartnerRequests(200)),
            'isAdmin' => CurrentUser::get()?->isAdministrator() ?? false,
        ], activeNav: self::NAV, title: 'Partner');
    }

    public function pool(Request $request): void
    {
        $this->renderPool($request, [], []);
    }

    public function sendMaterialRequest(Request $request): void
    {
        $partnerId = $request->param('id');
        $user = CurrentUser::get();
        try {
            $requestId = $this->commands->dispatch(new SendPartnerMaterialRequest(
                partnerId: $partnerId,
                rentalId: $request->input('rental_id'),
                eventName: $request->input('event_name', '') ?? '',
                startDate: $request->input('from', '') ?? '',
                endDate: $request->input('to', '') ?? '',
                eventLocation: $request->input('event_location', '') ?? '',
                note: $request->input('note', '') ?? '',
                quantities: $request->inputMap('quantity'),
                contactName: $user?->displayName(),
                contactEmail: $user?->email,
            ));
        } catch (ValidationException $e) {
            $this->renderPool($request, $e->errors(), $request->post);

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Flash::success('Die Anfrage wurde an den Partner geschickt.');
        Response::redirect('/partner/anfragen/' . $requestId);
    }

    public function technicians(Request $request): void
    {
        $this->renderTechnicians($request->param('id'), $request->query('rental'), [], []);
    }

    public function sendTechnicianRequest(Request $request): void
    {
        $partnerId = $request->param('id');
        $user = CurrentUser::get();
        $preferred = $request->input('preferred_technician', '') ?? '';
        [$preferredId, $preferredName] = array_pad(explode('|', $preferred, 2), 2, '');
        try {
            $requestId = $this->commands->dispatch(new SendPartnerTechnicianRequest(
                partnerId: $partnerId,
                rentalId: $request->input('rental_id'),
                eventName: $request->input('event_name', '') ?? '',
                startDate: $request->input('from', '') ?? '',
                endDate: $request->input('to', '') ?? '',
                eventLocation: $request->input('event_location', '') ?? '',
                note: $request->input('note', '') ?? '',
                trades: $request->inputArray('trades'),
                technicianCount: $request->input('technician_count', '1') ?? '1',
                preferredTechnicianId: $preferredId,
                preferredTechnicianName: $preferredName,
                contactName: $user?->displayName(),
                contactEmail: $user?->email,
            ));
        } catch (ValidationException $e) {
            $this->renderTechnicians($partnerId, $request->input('rental_id'), $e->errors(), $request->post);

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Flash::success('Die Techniker-Anfrage wurde an den Partner geschickt.');
        Response::redirect('/partner/anfragen/' . $requestId);
    }

    public function showRequest(Request $request): void
    {
        $this->renderRequest($request->param('id'), []);
    }

    public function acceptRequest(Request $request): void
    {
        $id = $request->param('id');
        $this->run($id, fn () => $this->commands->dispatch(new AcceptIncomingPartnerRequest(
            $id,
            $request->inputArray('technicians'),
            $request->inputArray('trades'),
            $request->input('message', '') ?? '',
        )), 'Die Anfrage wurde zugesagt und als Veranstaltung eingetragen.');
    }

    public function declineRequest(Request $request): void
    {
        $id = $request->param('id');
        $this->run($id, fn () => $this->commands->dispatch(new DeclineIncomingPartnerRequest($id, $request->input('message', '') ?? '')), 'Die Anfrage wurde abgelehnt.');
    }

    public function cancelRequest(Request $request): void
    {
        $id = $request->param('id');
        $this->run($id, fn () => $this->commands->dispatch(new CancelOutgoingPartnerRequest($id)), 'Die Anfrage wurde storniert.');
    }

    public function refreshRequest(Request $request): void
    {
        $id = $request->param('id');
        $this->run($id, fn () => $this->commands->dispatch(new RefreshOutgoingPartnerRequest($id)), 'Der Stand wurde beim Partner abgefragt.');
    }

    public function resendStatus(Request $request): void
    {
        $id = $request->param('id');
        try {
            $sent = $this->commands->dispatch(new ResendPartnerRequestStatus($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        $sent ? Flash::success('Der Partner wurde benachrichtigt.') : Flash::error('Der Partner konnte nicht benachrichtigt werden.');
        Response::redirect('/partner/anfragen/' . $id);
    }

    private function run(string $id, callable $action, string $success): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->renderRequest($id, $e->errors());

            return;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Flash::success($success);
        Response::redirect('/partner/anfragen/' . $id);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function renderPool(Request $request, array $errors, array $old): void
    {
        $partnerId = $request->param('id');
        $rental = $this->rental($request->input('rental_id') ?? $request->query('rental'));
        $from = $request->input('from') ?? $rental?->startDate->format('Y-m-d') ?? (new \DateTimeImmutable('today'))->format('Y-m-d');
        $to = $request->input('to') ?? $rental?->endDate->format('Y-m-d') ?? $from;
        try {
            $pool = $this->queries->ask(new BrowsePartnerPool($partnerId, $request->input('q'), $from, $to, max(1, (int) ($request->query('page') ?? '1'))));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        } catch (ValidationException $e) {
            $errors += $e->errors();
            $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
            $pool = $this->queries->ask(new BrowsePartnerPool($partnerId, $request->input('q'), $today, $today));
        }
        echo View::page('partners/pool', [
            'pool' => $pool,
            'rental' => $rental,
            'search' => $request->input('q') ?? '',
            'errors' => $errors,
            'old' => $old,
        ], activeNav: self::NAV, title: 'Materialpool: ' . $pool->partner->name);
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, mixed> $old
     */
    private function renderTechnicians(string $partnerId, ?string $rentalId, array $errors, array $old): void
    {
        try {
            $options = $this->queries->ask(new GetPartnerTechnicianOptions($partnerId));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        echo View::page('partners/technicians', [
            'options' => $options,
            'rental' => $this->rental($rentalId),
            'errors' => $errors,
            'old' => $old,
        ], activeNav: self::NAV, title: 'Techniker anfragen: ' . $options->partner->name);
    }

    /** @param array<string, string> $errors */
    private function renderRequest(string $id, array $errors): void
    {
        try {
            $detail = $this->queries->ask(new GetPartnerRequest($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        echo View::page('partners/request', [
            'detail' => $detail,
            'errors' => $errors,
        ], activeNav: self::NAV, title: 'Partner-Anfrage: ' . $detail->request->eventName);
    }

    private function rental(?string $rentalId): ?\App\Domain\Rental\Rental
    {
        if ($rentalId === null || trim($rentalId) === '') {
            return null;
        }
        try {
            return $this->queries->ask(new GetRental($rentalId))->rental;
        } catch (NotFoundException) {
            return null;
        }
    }
}
