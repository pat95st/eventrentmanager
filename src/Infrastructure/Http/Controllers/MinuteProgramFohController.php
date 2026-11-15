<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\MinuteProgram\Query\GetMinuteProgramDetail;
use App\Application\MinuteProgram\Query\MinuteProgramDetail;
use App\Application\MinuteProgram\Query\ResolveMinuteProgramShareLink;
use App\Application\Query\QueryBus;
use App\Domain\Exception\NotFoundException;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramShareLinkRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\TechnicianAccess;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * FOH run sheet ("Ablauf am FOH", migration 157): full-screen live page of a
 * minute program for the front of house, analogous to the live audio page
 * (EventAudioLiveController). Shows all information of the program and lets
 * the operator record the delay – either as an exact value or by pressing
 * "Start" on an item.
 *
 * Two ways in:
 *  - users (minute programs, issue/return, technicians – restricted
 *    technicians only for rentals they are booked on):
 *    /minutenprogramme/{id}/foh
 *  - share link token: /minutenprogramm/freigabe/{token}/foh; only
 *    read/write links may control, read links get a view-only page.
 */
final class MinuteProgramFohController
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly RunSheetActions $actions,
        private readonly RentalRepositoryInterface $rentals,
        private readonly MinuteProgramRepositoryInterface $programs,
        private readonly MinuteProgramShareLinkRepositoryInterface $shareLinks,
        private readonly ?RentalTechnicianRepositoryInterface $rentalTechnicians = null,
    ) {
    }

    // ---------------------------------------------------------------- users

    public function userPage(Request $request): void
    {
        $detail = $this->userDetail($request, json: false);
        if ($detail === null) {
            return;
        }
        $program = $detail->program;
        $others = [];
        foreach ($this->programs->findByRental($program->rentalId) as $other) {
            $others[] = ['id' => $other->id, 'name' => $other->name, 'room' => $other->room, 'startAt' => $other->startAt];
        }
        $apiUrls = [];
        foreach ($this->shareLinks->findByMinuteProgram($program->id) as $link) {
            if ($link->isActive()) {
                $apiUrls[] = ['url' => '/api/runsheet/v1/' . $link->token, 'mode' => $link->mode->label(), 'canEdit' => $link->mode->canEdit()];
            }
        }
        $this->render($detail, '/minutenprogramme/' . $program->id . '/foh', canControl: true, actorName: CurrentUser::get()?->displayName() ?? '', extra: [
            'backUrl' => '/minutenprogramme/' . $program->id,
            'otherPrograms' => count($others) > 1 ? $others : [],
            'apiUrls' => $apiUrls,
        ]);
    }

    public function userStatus(Request $request): void
    {
        $detail = $this->userDetail($request, json: true);
        if ($detail !== null) {
            Response::json($this->actions->payload($detail, true));
        }
    }

    public function userAction(Request $request): void
    {
        $detail = $this->userDetail($request, json: true);
        if ($detail !== null) {
            $this->act($request, $detail, fn (): ?MinuteProgramDetail => $this->queries->ask(new GetMinuteProgramDetail($detail->program->id)));
        }
    }

    // ---------------------------------------------------------------- share link

    public function sharePage(Request $request): void
    {
        $resolved = $this->resolveShare($request, json: false);
        if ($resolved === null) {
            return;
        }
        $this->render($resolved->detail, '/minutenprogramm/freigabe/' . $request->param('token') . '/foh', canControl: $resolved->link->mode->canEdit(), actorName: '', extra: [
            'backUrl' => '/minutenprogramm/freigabe/' . $request->param('token'),
            'otherPrograms' => [],
            'apiUrls' => [['url' => '/api/runsheet/v1/' . $request->param('token'), 'mode' => $resolved->link->mode->label(), 'canEdit' => $resolved->link->mode->canEdit()]],
        ]);
    }

    public function shareStatus(Request $request): void
    {
        $resolved = $this->resolveShare($request, json: true);
        if ($resolved !== null) {
            Response::json($this->actions->payload($resolved->detail, $resolved->link->mode->canEdit()));
        }
    }

    public function shareAction(Request $request): void
    {
        $resolved = $this->resolveShare($request, json: true);
        if ($resolved === null) {
            return;
        }
        if (!$resolved->link->mode->canEdit()) {
            Response::json(['ok' => false, 'error' => 'Dieser Freigabe-Link erlaubt keine Änderungen.'], 403);

            return;
        }
        $token = $request->param('token');
        $this->act($request, $resolved->detail, fn (): ?MinuteProgramDetail => $this->queries->ask(new ResolveMinuteProgramShareLink($token))->detail);
    }

    // ---------------------------------------------------------------- shared

    /** @param callable(): ?MinuteProgramDetail $reload */
    private function act(Request $request, MinuteProgramDetail $detail, callable $reload): void
    {
        $error = $this->actions->perform($request->param('action'), $detail, RunSheetActions::input($request), $request->routeParams['itemId'] ?? null);
        if ($error !== null) {
            Response::json(['ok' => false, 'error' => $error[0]], $error[1]);

            return;
        }
        $fresh = $reload();
        Response::json(['ok' => true] + ($fresh !== null ? $this->actions->payload($fresh, true) : []));
    }

    /** @param array<string, mixed> $extra */
    private function render(MinuteProgramDetail $detail, string $basePath, bool $canControl, string $actorName, array $extra): void
    {
        $rental = $this->rentals->findById($detail->program->rentalId);
        echo View::render('minute-programs/foh', $extra + [
            'detail' => $detail,
            'rentalName' => $rental?->eventName ?? '',
            'basePath' => $basePath,
            'canControl' => $canControl,
            'actorName' => $actorName,
            'initialPayload' => $this->actions->payload($detail, $canControl),
            'csrfToken' => Csrf::token(),
        ]);
    }

    private function userDetail(Request $request, bool $json): ?MinuteProgramDetail
    {
        try {
            /** @var MinuteProgramDetail $detail */
            $detail = $this->queries->ask(new GetMinuteProgramDetail($request->param('id')));
        } catch (NotFoundException) {
            $detail = null;
        }
        $user = CurrentUser::get();
        $allowed = $detail !== null && $user !== null
            && (!TechnicianAccess::isRestricted($user)
                || TechnicianAccess::canSeeRental($this->rentalTechnicians?->findByRentalAndUser($detail->program->rentalId, $user->id)));
        if ($allowed) {
            return $detail;
        }
        if ($json) {
            Response::json(['ok' => false, 'error' => 'Das Minutenprogramm wurde nicht gefunden.'], 404);
        } else {
            Response::notFound('Das Minutenprogramm wurde nicht gefunden.');
        }

        return null;
    }

    private function resolveShare(Request $request, bool $json): ?\App\Application\MinuteProgram\Query\ResolvedMinuteProgramShareLink
    {
        try {
            return $this->queries->ask(new ResolveMinuteProgramShareLink($request->param('token')));
        } catch (NotFoundException) {
            if ($json) {
                Response::json(['ok' => false, 'error' => 'Der Freigabe-Link ist nicht (mehr) gültig.'], 404);
            } else {
                http_response_code(404);
                echo View::render('public/minute-program-share-unavailable');
            }

            return null;
        }
    }
}
