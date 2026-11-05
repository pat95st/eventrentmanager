<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Rma\Command\CreateRma;
use App\Application\Rental\Query\GetRental;
use App\Application\Query\QueryBus;
use App\Application\Technician\Command\AssignTechnician;
use App\Application\Technician\Command\NotifyTechnician;
use App\Application\Technician\Command\RemoveRentalTechnician;
use App\Application\Technician\Command\RespondToTechnicianRequest;
use App\Application\Technician\Command\SetRentalTrades;
use App\Application\Technician\Command\UpdateRentalTechnician;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\RentalTechnicianStatus;
use App\Domain\Technician\TechnicianAccess;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Techniker & Gewerke an einer Veranstaltung (Migration 134): Planung durch
 * Disponenten (Ausgabe/Rücknahme), Zu-/Absage und RMA-Meldung durch die
 * Techniker selbst.
 */
final class RentalTechnicianController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly RentalTechnicianRepositoryInterface $assignments,
    ) {
    }

    public function setTrades(Request $request): void
    {
        $rentalId = $request->param('id');
        $this->commands->dispatch(new SetRentalTrades($rentalId, $request->inputArray('trade_ids')));
        Flash::success('Die benötigten Gewerke wurden gespeichert.');
        Response::redirect('/veranstaltungen/' . $rentalId . '#techniker');
    }

    public function assign(Request $request): void
    {
        $rentalId = $request->param('id');
        try {
            $result = $this->commands->dispatch(new AssignTechnician(
                $rentalId,
                $request->input('user_id', '') ?? '',
                $request->inputArray('trade_ids'),
                $request->input('note'),
            ));
            $verb = $result['status'] === RentalTechnicianStatus::Assigned ? 'eingeplant' : 'angefragt';
            Flash::success('Techniker wurde ' . $verb . '.' . $this->notificationSummary($result['notification']));
            if ($result['notification'] !== null && $result['notification']['errors'] !== []) {
                Flash::error('Benachrichtigung teilweise fehlgeschlagen: ' . implode(' · ', $result['notification']['errors']));
            }
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }
        Response::redirect('/veranstaltungen/' . $rentalId . '#techniker');
    }

    public function update(Request $request): void
    {
        try {
            $rentalId = $this->commands->dispatch(new UpdateRentalTechnician($request->param('id'), $request->inputArray('trade_ids'), $request->input('note')));
            Flash::success('Gewerke des Technikers wurden gespeichert.');
            Response::redirect('/veranstaltungen/' . $rentalId . '#techniker');
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function remove(Request $request): void
    {
        try {
            $rentalId = $this->commands->dispatch(new RemoveRentalTechnician($request->param('id')));
            Flash::success('Techniker wurde von der Veranstaltung entfernt.');
            Response::redirect('/veranstaltungen/' . $rentalId . '#techniker');
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function notify(Request $request): void
    {
        $assignment = $this->assignments->findById($request->param('id'));
        if ($assignment === null) {
            Response::notFound('Die Zuordnung wurde nicht gefunden.');

            return;
        }
        $result = $this->commands->dispatch(new NotifyTechnician($assignment->id, $assignment->status === RentalTechnicianStatus::Requested));
        if ($result['email'] || $result['channels'] !== []) {
            Flash::success('Techniker wurde erneut benachrichtigt.' . $this->notificationSummary($result));
        }
        if ($result['errors'] !== []) {
            Flash::error('Benachrichtigung fehlgeschlagen: ' . implode(' · ', $result['errors']));
        }
        Response::redirect('/veranstaltungen/' . $assignment->rentalId . '#techniker');
    }

    /** Zu- oder Absage durch den Techniker selbst. */
    public function respond(Request $request): void
    {
        try {
            $rentalId = $this->commands->dispatch(new RespondToTechnicianRequest(
                $request->param('id'),
                CurrentUser::get()?->id ?? '',
                $request->input('answer') === 'zusagen',
            ));
            Flash::success($request->input('answer') === 'zusagen' ? 'Danke für deine Zusage!' : 'Deine Absage wurde gespeichert.');
            $back = $request->input('back');
            Response::redirect($back === 'liste' || $request->input('answer') !== 'zusagen' && TechnicianAccess::isRestricted(CurrentUser::get()) ? '/veranstaltungen' : '/veranstaltungen/' . $rentalId);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/veranstaltungen');
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    /** RMA-Meldung eines Technikers für ein in dieser Veranstaltung ausgegebenes Gerät. */
    public function rmaForm(Request $request): void
    {
        $rentalId = $request->param('id');
        $detail = $this->accessibleRental($rentalId);
        if ($detail === null) {
            return;
        }
        echo View::page('technicians/rma', ['detail' => $detail], activeNav: 'rentals', title: 'Defekt melden · ' . $detail->rental->eventName);
    }

    public function storeRma(Request $request): void
    {
        $rentalId = $request->param('id');
        $detail = $this->accessibleRental($rentalId);
        if ($detail === null) {
            return;
        }
        $inventoryNumberId = $request->input('inventory_number_id', '') ?? '';
        $allowed = array_map(static fn ($issued) => $issued->inventoryNumber->id, $detail->issuedItems);
        if (!in_array($inventoryNumberId, $allowed, true)) {
            Flash::error('Bitte ein Gerät aus dieser Veranstaltung auswählen.');
            Response::redirect('/veranstaltungen/' . $rentalId . '/rma');

            return;
        }
        $reason = trim($request->input('reason', '') ?? '');
        if ($reason === '') {
            Flash::error('Bitte beschreibe den Defekt.');
            Response::redirect('/veranstaltungen/' . $rentalId . '/rma');

            return;
        }
        $user = CurrentUser::get();
        try {
            $this->commands->dispatch(new CreateRma(
                inventoryNumberId: $inventoryNumberId,
                reason: $reason,
                serviceProvider: null,
                rmaReference: null,
                sentAt: null,
                returnedAt: null,
                notes: 'Gemeldet von ' . ($user?->displayName() ?? 'Techniker') . ' bei Veranstaltung „' . $detail->rental->eventName . '“ (' . date('d.m.Y H:i') . ')'
                    . (trim($request->input('notes', '') ?? '') !== '' ? "\n" . trim($request->input('notes', '') ?? '') : ''),
                kind: 'eigenreparatur',
                workedHours: null,
                receiptIds: [],
            ));
            Flash::success('Der Defekt wurde als RMA-Vorgang gemeldet.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }
        Response::redirect('/veranstaltungen/' . $rentalId);
    }

    private function accessibleRental(string $rentalId): ?\App\Application\Rental\Query\RentalDetail
    {
        $user = CurrentUser::get();
        if (TechnicianAccess::isRestricted($user) && !TechnicianAccess::canSeeRental($this->assignments->findByRentalAndUser($rentalId, $user->id))) {
            Response::notFound('Die Veranstaltung wurde nicht gefunden.');

            return null;
        }
        try {
            return $this->queries->ask(new GetRental($rentalId));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return null;
        }
    }

    /** @param array{email: bool, channels: string[], errors: string[]}|null $notification */
    private function notificationSummary(?array $notification): string
    {
        if ($notification === null) {
            return '';
        }
        $ways = array_merge($notification['email'] ? ['E-Mail mit Termineinladung'] : [], $notification['channels']);

        return $ways === [] ? '' : ' Benachrichtigt per ' . implode(', ', $ways) . '.';
    }
}
