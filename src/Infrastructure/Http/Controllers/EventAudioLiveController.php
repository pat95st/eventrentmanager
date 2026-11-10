<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\EventAudio\Command\AuthenticateOrganizer;
use App\Application\EventAudio\Command\CancelPlayback;
use App\Application\EventAudio\Command\CancelScheduledPlayback;
use App\Application\EventAudio\Command\ReplayPlayback;
use App\Application\EventAudio\Command\RequestPlayback;
use App\Application\EventAudio\Command\SchedulePlayback;
use App\Application\EventAudio\ScheduledPlaybackDispatcher;
use App\Domain\EventAudio\AudioFileKind;
use App\Domain\EventAudio\AudioPlayerRepositoryInterface;
use App\Domain\EventAudio\AudioSettingsRepositoryInterface;
use App\Domain\EventAudio\OrganizerLogin;
use App\Domain\EventAudio\OrganizerLoginRepositoryInterface;
use App\Domain\EventAudio\PlaybackRequest;
use App\Domain\EventAudio\PlaybackRequestRepositoryInterface;
use App\Domain\EventAudio\RentalAudioFileRepositoryInterface;
use App\Domain\EventAudio\RentalAudioSettingsRepositoryInterface;
use App\Domain\EventAudio\ScheduledPlayback;
use App\Domain\EventAudio\ScheduledPlaybackRepositoryInterface;
use App\Domain\EventAudio\ScheduledPlaybackStatus;
use App\Domain\EventAudio\PlaybackKind;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\TechnicianAccess;
use App\Domain\EventAudio\RentalAudioFile;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use DateTimeImmutable;

/**
 * Live-Oberfläche „Audio & Durchsagen“ (Migration 135).
 *
 * Zwei Zugangswege auf dieselbe Oberfläche:
 *  - Veranstalter: eigener, zeitlich begrenzbarer Zugang je Veranstaltung
 *    (/live/login → /live/{id}), getrennt von den Benutzerkonten;
 *  - Benutzer (Admins, Disposition, Minutenprogramme sowie eingeplante
 *    Techniker): /veranstaltungen/{id}/live, geschützt über AuthGuard.
 *
 * Die Oberfläche legt nur Abspielaufträge an; abgespielt wird lokal vom
 * zugeordneten Abspielgerät (Raspberry Pi am Mischpult), siehe
 * AudioPlayerApiController.
 */
final class EventAudioLiveController
{
    private const SESSION_ORGANIZER = 'event_audio_organizer';

    /** Benutzer (Admins/Techniker) sehen zusätzlich Details wie den letzten Sync des Abspielgeräts. */
    private bool $detailed = false;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly RentalRepositoryInterface $rentals,
        private readonly RentalAudioFileRepositoryInterface $files,
        private readonly RentalAudioSettingsRepositoryInterface $rentalSettings,
        private readonly AudioPlayerRepositoryInterface $players,
        private readonly AudioSettingsRepositoryInterface $settings,
        private readonly OrganizerLoginRepositoryInterface $logins,
        private readonly PlaybackRequestRepositoryInterface $requests,
        private readonly ?RentalTechnicianRepositoryInterface $rentalTechnicians = null,
        private readonly ?MinuteProgramRepositoryInterface $minutePrograms = null,
        private readonly ?ScheduledPlaybackRepositoryInterface $schedules = null,
        private readonly ?ScheduledPlaybackDispatcher $dispatcher = null,
        private readonly ?LocalFileStorage $storage = null,
    ) {
    }

    // ---------------------------------------------------------------- Veranstalter-Login

    public function loginForm(Request $request): void
    {
        $current = $this->currentOrganizer();
        if ($current !== null) {
            Response::redirect('/live/' . $current->rentalId);

            return;
        }
        echo View::render('event-audio/login', ['error' => null, 'username' => '']);
    }

    public function login(Request $request): void
    {
        $username = trim((string) $request->input('username', ''));
        try {
            /** @var OrganizerLogin $login */
            $login = $this->commands->dispatch(new AuthenticateOrganizer($username, (string) $request->input('password', '')));
        } catch (ValidationException $e) {
            http_response_code(422);
            echo View::render('event-audio/login', ['error' => implode(' ', $e->errors()), 'username' => $username]);

            return;
        }
        Session::ensureStarted();
        session_regenerate_id(true);
        $_SESSION[self::SESSION_ORGANIZER] = ['login' => $login->id, 'rental' => $login->rentalId];
        Csrf::regenerate();
        Response::redirect('/live/' . $login->rentalId);
    }

    public function logout(Request $request): void
    {
        Session::ensureStarted();
        unset($_SESSION[self::SESSION_ORGANIZER]);
        session_regenerate_id(true);
        Response::redirect('/live/login');
    }

    // ---------------------------------------------------------------- Seite / Aktionen (Veranstalter)

    public function organizerPage(Request $request): void
    {
        $login = $this->requireOrganizer($request, json: false);
        if ($login === null) {
            return;
        }
        $this->renderPage($request->param('id'), '/live/' . $login->rentalId, $login->label, organizer: true, canSchedule: $login->canSchedule);
    }

    public function organizerStatus(Request $request): void
    {
        if ($this->requireOrganizer($request, json: true) !== null) {
            $this->status($request->param('id'));
        }
    }

    public function organizerPlay(Request $request): void
    {
        $login = $this->requireOrganizer($request, json: true);
        if ($login !== null) {
            $this->play($request, $request->param('id'), 'organizer', $login->id, $login->label . ' (Veranstalter)');
        }
    }

    public function organizerCancel(Request $request): void
    {
        if ($this->requireOrganizer($request, json: true) !== null) {
            $this->cancel($request, $request->param('id'));
        }
    }

    public function organizerSchedule(Request $request): void
    {
        $login = $this->requireOrganizer($request, json: true);
        if ($login === null) {
            return;
        }
        if (!$login->canSchedule) {
            Response::json(['ok' => false, 'error' => 'Dein Zugang darf keine Einspieler planen.'], 403);

            return;
        }
        $this->schedule($request, $request->param('id'), 'organizer', $login->id, $login->label . ' (Veranstalter)');
    }

    public function organizerCancelSchedule(Request $request): void
    {
        $login = $this->requireOrganizer($request, json: true);
        if ($login === null) {
            return;
        }
        if (!$login->canSchedule) {
            Response::json(['ok' => false, 'error' => 'Dein Zugang darf keine Einspieler planen.'], 403);

            return;
        }
        $this->cancelSchedule($request, $request->param('id'));
    }

    public function organizerReplay(Request $request): void
    {
        $login = $this->requireOrganizer($request, json: true);
        if ($login !== null) {
            $this->replay($request, $request->param('id'), 'organizer', $login->id, $login->label . ' (Veranstalter)');
        }
    }

    /** Logo der Veranstaltung – nur für angemeldete Veranstalter dieser Veranstaltung. */
    public function organizerLogo(Request $request): void
    {
        if ($this->requireOrganizer($request, json: false) === null) {
            return;
        }
        $settings = $this->rentalSettings->get($request->param('id'));
        if ($settings->logoFilename === null || $this->storage === null) {
            Response::notFound('Kein Logo hinterlegt.');

            return;
        }
        Response::cachedFile($this->storage->absolutePath(RentalAudioFile::storageDirectory($settings->rentalId), $settings->logoFilename), $settings->logoFilename, EventAudioController::logoMime($settings->logoFilename), $request->query('v') !== null);
    }

    // ---------------------------------------------------------------- Seite / Aktionen (Benutzer)

    public function userPage(Request $request): void
    {
        if ($this->requireUserAccess($request, json: false)) {
            $this->renderPage($request->param('id'), '/veranstaltungen/' . $request->param('id') . '/live', CurrentUser::get()?->displayName() ?? '', organizer: false, canSchedule: true);
        }
    }

    public function userStatus(Request $request): void
    {
        if ($this->requireUserAccess($request, json: true)) {
            $this->status($request->param('id'));
        }
    }

    public function userPlay(Request $request): void
    {
        if ($this->requireUserAccess($request, json: true)) {
            $user = CurrentUser::get();
            $this->play($request, $request->param('id'), 'user', $user?->id, $user?->displayName() ?? 'Benutzer');
        }
    }

    public function userCancel(Request $request): void
    {
        if ($this->requireUserAccess($request, json: true)) {
            $this->cancel($request, $request->param('id'));
        }
    }

    public function userSchedule(Request $request): void
    {
        if ($this->requireUserAccess($request, json: true)) {
            $user = CurrentUser::get();
            $this->schedule($request, $request->param('id'), 'user', $user?->id, $user?->displayName() ?? 'Benutzer');
        }
    }

    public function userCancelSchedule(Request $request): void
    {
        if ($this->requireUserAccess($request, json: true)) {
            $this->cancelSchedule($request, $request->param('id'));
        }
    }

    public function userReplay(Request $request): void
    {
        if ($this->requireUserAccess($request, json: true)) {
            $user = CurrentUser::get();
            $this->replay($request, $request->param('id'), 'user', $user?->id, $user?->displayName() ?? 'Benutzer');
        }
    }

    // ---------------------------------------------------------------- gemeinsam

    private function renderPage(string $rentalId, string $basePath, string $actorName, bool $organizer, bool $canSchedule = false): void
    {
        $this->detailed = !$organizer;
        $rental = $this->rentals->findById($rentalId);
        if ($rental === null) {
            Response::notFound('Die Veranstaltung wurde nicht gefunden.');

            return;
        }
        $rentalSettings = $this->rentalSettings->get($rentalId);
        $settings = $this->settings->get();
        $jingles = $this->files->findByRental($rentalId, AudioFileKind::Jingle);
        $jingleTitles = [];
        foreach ($jingles as $jingle) {
            $jingleTitles[$jingle->id] = $jingle->title;
        }
        // Minutenprogramme mit gewähltem Jingle → Schnellwahl „Jingle für …“.
        $programs = [];
        foreach ($this->minutePrograms?->findByRental($rentalId) ?? [] as $program) {
            if ($program->jingleBefore && $program->jingleAudioFileId !== null && isset($jingleTitles[$program->jingleAudioFileId])) {
                $programs[] = [
                    'name' => $program->name,
                    'startAt' => $program->startAt->modify(($program->currentDelayMinutes >= 0 ? '+' : '') . $program->currentDelayMinutes . ' minutes'),
                    'jingleId' => $program->jingleAudioFileId,
                    'jingleTitle' => $jingleTitles[$program->jingleAudioFileId],
                ];
            }
        }
        usort($programs, static fn ($a, $b) => $a['startAt'] <=> $b['startAt']);

        echo View::render('event-audio/live', [
            'rental' => $rental,
            'basePath' => $basePath,
            'actorName' => $actorName,
            'organizer' => $organizer,
            'jingles' => $jingles,
            'programs' => $programs,
            'audioFiles' => $this->files->findByRental($rentalId, AudioFileKind::Audio),
            'gongByDefault' => $rentalSettings->gongByDefault,
            'hasPlayer' => $rentalSettings->playerId !== null,
            'ttsLanguage' => $settings->ttsLanguage,
            'canSchedule' => $canSchedule && $this->schedules !== null,
            // Eigenes Veranstaltungs-Logo nur für Veranstalter; Techniker/Admins sehen immer das Firmenlogo.
            'customLogoUrl' => $organizer && $rentalSettings->logoFilename !== null
                ? $basePath . '/logo?v=' . substr(md5($rentalSettings->logoFilename), 0, 10)
                : null,
            'maxUploadMb' => $settings->maxUploadMb,
            'rentalWindow' => [$rental->startDate->modify('-1 day')->format('Y-m-d') . 'T00:00', $rental->endDate->modify('+1 day')->format('Y-m-d') . 'T23:59'],
            'initialStatus' => $this->statusPayload($rentalId),
            'csrfToken' => Csrf::token(),
        ]);
    }

    private function status(string $rentalId): void
    {
        Response::json($this->statusPayload($rentalId));
    }

    /** @return array<string, mixed> */
    private function statusPayload(string $rentalId): array
    {
        $rentalSettings = $this->rentalSettings->get($rentalId);
        $player = $rentalSettings->playerId !== null ? $this->players->findById($rentalSettings->playerId) : null;
        $now = new DateTimeImmutable();
        $expiry = max(1, $this->settings->get()->queueExpiryMinutes);
        $this->dispatcher?->dispatchDue($rentalId, $now, $expiry);
        $this->requests->expireQueuedBefore($rentalId, $now->modify('-' . $expiry . ' minutes'));
        $fileTitles = [];
        foreach ($this->files->findByRental($rentalId) as $file) {
            $fileTitles[$file->id] = $file->title;
        }

        return [
            'player' => $player === null ? null : [
                'name' => $player->name,
                'online' => $player->isOnline($now),
                'nowPlaying' => $player->isOnline($now) ? $player->nowPlaying : null,
                'lastSeen' => $player->lastSeenAt?->format('d.m.Y H:i:s'),
                // Nur für Benutzer (Admins/Techniker): letzter Sync des Abspielgeräts
                'lastSync' => $this->detailed ? $player->lastSeenAt?->format('H:i:s') : null,
                'lastSyncDate' => $this->detailed ? $player->lastSeenAt?->format('d.m.Y') : null,
                'lastSyncAgo' => $this->detailed && $player->lastSeenAt !== null ? max(0, $now->getTimestamp() - $player->lastSeenAt->getTimestamp()) : null,
            ],
            'detailed' => $this->detailed,
            'requests' => array_map(static fn (PlaybackRequest $r): array => [
                'id' => $r->id,
                'kind' => $r->kind->value,
                'kindLabel' => $r->kind->label(),
                'label' => $r->label ?? $r->kind->label(),
                'gong' => $r->withGong,
                'status' => $r->status->value,
                'statusLabel' => $r->status->label(),
                'open' => $r->status->isOpen(),
                'by' => $r->requestedByName,
                'time' => $r->createdAt->format('H:i:s'),
                'error' => $r->error,
                'replayable' => !$r->status->isOpen() && $r->kind !== PlaybackKind::Stop,
            ], $this->requests->recentForRental($rentalId, 15)),
            'schedules' => array_map(static fn (ScheduledPlayback $s): array => [
                'id' => $s->id,
                'title' => $fileTitles[$s->audioFileId] ?? 'Audio-Datei',
                'note' => $s->note,
                'date' => $s->playAt->format('d.m.'),
                'time' => $s->playAt->format('H:i'),
                'today' => $s->playAt->format('Y-m-d') === $now->format('Y-m-d'),
                'status' => $s->status->value,
                'statusLabel' => $s->status->label(),
                'by' => $s->createdByName,
                'replayable' => $s->status === ScheduledPlaybackStatus::Triggered || $s->status === ScheduledPlaybackStatus::Missed,
            ], $this->schedules?->findForRental($rentalId, 5) ?? []),
        ];
    }

    private function play(Request $request, string $rentalId, string $byType, ?string $byId, string $byName): void
    {
        try {
            $id = $this->commands->dispatch(new RequestPlayback(
                $rentalId,
                (string) $request->input('kind', ''),
                $request->input('audio_file_id'),
                $request->input('text'),
                $request->file('recording'),
                $request->input('with_gong') === '1',
                $byType,
                $byId,
                $byName,
            ));
        } catch (ValidationException $e) {
            Response::json(['ok' => false, 'error' => implode(' ', $e->errors())], 422);

            return;
        }
        $warning = $this->rentalSettings->get($rentalId)->playerId === null
            ? 'Dieser Veranstaltung ist noch kein Abspielgerät zugeordnet – der Auftrag wartet, bis eines zugeordnet ist.'
            : null;
        Response::json(['ok' => true, 'id' => $id, 'warning' => $warning] + $this->statusPayload($rentalId));
    }

    private function cancel(Request $request, string $rentalId): void
    {
        try {
            $this->commands->dispatch(new CancelPlayback($request->param('requestId'), $rentalId));
        } catch (NotFoundException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 404);

            return;
        }
        Response::json(['ok' => true] + $this->statusPayload($rentalId));
    }

    private function schedule(Request $request, string $rentalId, string $byType, ?string $byId, string $byName): void
    {
        $upload = $request->file('file');
        try {
            $ids = $this->commands->dispatch(new SchedulePlayback(
                $rentalId,
                $request->input('audio_file_id'),
                $upload !== null && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $upload : null,
                (string) $request->input('title', ''),
                $request->inputArray('play_at') !== [] ? $request->inputArray('play_at') : [(string) $request->input('play_at', '')],
                $request->input('note'),
                $byType,
                $byId,
                $byName,
            ));
        } catch (ValidationException $e) {
            Response::json(['ok' => false, 'error' => implode(' ', $e->errors())], 422);

            return;
        }
        Response::json(['ok' => true, 'count' => is_array($ids) ? count($ids) : 1] + $this->statusPayload($rentalId));
    }

    private function cancelSchedule(Request $request, string $rentalId): void
    {
        try {
            $this->commands->dispatch(new CancelScheduledPlayback($request->param('scheduleId'), $rentalId));
        } catch (NotFoundException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 404);

            return;
        }
        Response::json(['ok' => true] + $this->statusPayload($rentalId));
    }

    /**
     * Erneut einspielen – Route .../erneut/{requestId} (Verlauf) oder
     * .../planung/{scheduleId}/erneut (geplanter Einspieler; ausgelöste
     * wiederholen ihren Auftrag, verpasste spielen die Datei jetzt).
     */
    private function replay(Request $request, string $rentalId, string $byType, ?string $byId, string $byName): void
    {
        try {
            $scheduleId = $request->routeParams['scheduleId'] ?? null;
            if ($scheduleId !== null) {
                $schedule = $this->schedules?->findById((string) $scheduleId);
                if ($schedule === null || $schedule->rentalId !== $rentalId || $schedule->status === ScheduledPlaybackStatus::Pending) {
                    throw new NotFoundException('Der Einspieler wurde nicht gefunden oder ist noch geplant.');
                }
                $command = $schedule->playbackRequestId !== null && $this->requests->findById($schedule->playbackRequestId) !== null
                    ? new ReplayPlayback($schedule->playbackRequestId, $rentalId, $byType, $byId, $byName)
                    : new RequestPlayback($rentalId, 'audio', $schedule->audioFileId, null, null, false, $byType, $byId, $byName);
            } else {
                $command = new ReplayPlayback($request->param('requestId'), $rentalId, $byType, $byId, $byName);
            }
            $this->commands->dispatch($command);
        } catch (ValidationException $e) {
            Response::json(['ok' => false, 'error' => implode(' ', $e->errors())], 422);

            return;
        } catch (NotFoundException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 404);

            return;
        }
        Response::json(['ok' => true] + $this->statusPayload($rentalId));
    }

    private function currentOrganizer(): ?OrganizerLogin
    {
        Session::ensureStarted();
        $data = $_SESSION[self::SESSION_ORGANIZER] ?? null;
        if (!is_array($data) || !isset($data['login'])) {
            return null;
        }
        $login = $this->logins->findById((string) $data['login']);
        if ($login === null || !$login->isValidAt(new DateTimeImmutable())) {
            unset($_SESSION[self::SESSION_ORGANIZER]);

            return null;
        }

        return $login;
    }

    private function requireOrganizer(Request $request, bool $json): ?OrganizerLogin
    {
        $this->detailed = false;
        $login = $this->currentOrganizer();
        if ($login !== null && $login->rentalId === $request->param('id')) {
            return $login;
        }
        if ($json) {
            Response::json(['ok' => false, 'error' => 'Deine Anmeldung ist abgelaufen. Bitte neu anmelden.', 'relogin' => true], 401);
        } else {
            Response::redirect('/live/login');
        }

        return null;
    }

    /** Eingeschränkte Techniker nur für Veranstaltungen, für die sie bestätigt/eingeplant sind. */
    private function requireUserAccess(Request $request, bool $json): bool
    {
        $rental = $this->rentals->findById($request->param('id'));
        $user = CurrentUser::get();
        $allowed = $rental instanceof Rental && $user !== null
            && (!TechnicianAccess::isRestricted($user)
                || TechnicianAccess::canSeeRental($this->rentalTechnicians?->findByRentalAndUser($rental->id, $user->id)));
        if ($allowed) {
            $this->detailed = true;

            return true;
        }
        if ($json) {
            Response::json(['ok' => false, 'error' => 'Die Veranstaltung wurde nicht gefunden.'], 404);
        } else {
            Response::notFound('Die Veranstaltung wurde nicht gefunden.');
        }

        return false;
    }
}
