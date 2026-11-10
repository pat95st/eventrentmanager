<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\EventAudio\Command\CancelScheduledPlayback;
use App\Application\EventAudio\Command\CreateOrganizerLogin;
use App\Application\EventAudio\Command\SchedulePlayback;
use App\Application\EventAudio\ScheduledPlaybackDispatcher;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Application\EventAudio\Command\DeleteOrganizerLogin;
use App\Application\EventAudio\Command\DeleteRentalAudioFile;
use App\Application\EventAudio\Command\SaveRentalAudioSettings;
use App\Application\EventAudio\Command\UpdateOrganizerLogin;
use App\Application\EventAudio\Command\UpdateRentalAudioFile;
use App\Application\EventAudio\Command\UploadRentalAudioFile;
use App\Domain\EventAudio\AudioFileKind;
use App\Domain\EventAudio\AudioPlayerRepositoryInterface;
use App\Domain\EventAudio\AudioSettingsRepositoryInterface;
use App\Domain\EventAudio\OrganizerLoginRepositoryInterface;
use App\Domain\EventAudio\PlaybackRequestRepositoryInterface;
use App\Domain\EventAudio\RentalAudioFile;
use App\Domain\EventAudio\RentalAudioFileRepositoryInterface;
use App\Domain\EventAudio\RentalAudioSettingsRepositoryInterface;
use App\Domain\EventAudio\ScheduledPlaybackRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Veranstaltung › Audio & Durchsagen (Migration 135): Jingles und weitere
 * Audio-Dateien hochladen, Abspielgerät zuordnen, Veranstalter-Zugänge
 * (zeitlich begrenzbar) verwalten und das Abspielprotokoll einsehen.
 */
final class EventAudioController
{
    private const SESSION_CREDENTIALS = 'event_audio_new_credentials';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly RentalRepositoryInterface $rentals,
        private readonly RentalAudioFileRepositoryInterface $files,
        private readonly RentalAudioSettingsRepositoryInterface $rentalSettings,
        private readonly AudioPlayerRepositoryInterface $players,
        private readonly AudioSettingsRepositoryInterface $settings,
        private readonly OrganizerLoginRepositoryInterface $logins,
        private readonly PlaybackRequestRepositoryInterface $requests,
        private readonly LocalFileStorage $storage,
        private readonly ?ScheduledPlaybackRepositoryInterface $schedules = null,
        private readonly ?ScheduledPlaybackDispatcher $dispatcher = null,
    ) {
    }

    public function show(Request $request): void
    {
        $rental = $this->rentals->findById($request->param('id'));
        if ($rental === null) {
            Response::notFound('Die Veranstaltung wurde nicht gefunden.');

            return;
        }
        Session::ensureStarted();
        $credentials = $_SESSION[self::SESSION_CREDENTIALS] ?? null;
        unset($_SESSION[self::SESSION_CREDENTIALS]);
        $rentalSettings = $this->rentalSettings->get($rental->id);
        $this->dispatcher?->dispatchDue($rental->id, new \DateTimeImmutable(), max(1, $this->settings->get()->queueExpiryMinutes));

        echo View::page('event-audio/manage', [
            'schedules' => $this->schedules?->findForRental($rental->id, 10),
            'rental' => $rental,
            'jingles' => $this->files->findByRental($rental->id, AudioFileKind::Jingle),
            'audioFiles' => $this->files->findByRental($rental->id, AudioFileKind::Audio),
            'rentalSettings' => $rentalSettings,
            'players' => $this->players->findAll(),
            'player' => $rentalSettings->playerId !== null ? $this->players->findById($rentalSettings->playerId) : null,
            'settings' => $this->settings->get(),
            'logins' => $this->logins->findByRental($rental->id),
            'recent' => $this->requests->recentForRental($rental->id, 30),
            'newCredentials' => is_array($credentials) && ($credentials['rental'] ?? null) === $rental->id ? $credentials : null,
        ], activeNav: 'rentals', title: 'Audio & Durchsagen – ' . $rental->eventName);
    }

    public function upload(Request $request): void
    {
        $rentalId = $this->requireRental($request);
        $uploads = $request->fileList('files');
        if ($uploads === [] && ($single = $request->file('file')) !== null) {
            $uploads = [$single];
        }
        if ($uploads === []) {
            Flash::error('Bitte mindestens eine Audio-Datei auswählen.');
            $this->back($rentalId, 'dateien');

            return;
        }
        $kind = $request->input('kind', AudioFileKind::Jingle->value) ?? AudioFileKind::Jingle->value;
        $single = count($uploads) === 1;
        $ok = 0;
        $errors = [];
        foreach ($uploads as $upload) {
            try {
                $this->commands->dispatch(new UploadRentalAudioFile(
                    $rentalId,
                    $kind,
                    $single ? ($request->input('title', '') ?? '') : '',
                    $single ? $request->input('description') : null,
                    $upload,
                ));
                $ok++;
            } catch (ValidationException $e) {
                $errors[] = implode(' ', $e->errors());
            }
        }
        if ($ok > 0) {
            Flash::success($ok === 1 ? 'Die Audio-Datei wurde hochgeladen.' : $ok . ' Audio-Dateien wurden hochgeladen.');
        }
        if ($errors !== []) {
            Flash::error(implode(' ', $errors));
        }
        $this->back($rentalId, 'dateien');
    }

    public function updateFile(Request $request): void
    {
        $rentalId = $this->requireRental($request);
        $this->run(fn () => $this->commands->dispatch(new UpdateRentalAudioFile(
            $request->param('fileId'),
            $rentalId,
            $request->input('title', '') ?? '',
            $request->input('description'),
            $request->input('kind', '') ?? '',
        )), 'Die Audio-Datei wurde gespeichert.', $rentalId, 'dateien');
    }

    public function deleteFile(Request $request): void
    {
        $rentalId = $this->requireRental($request);
        $this->run(fn () => $this->commands->dispatch(new DeleteRentalAudioFile($request->param('fileId'), $rentalId)), 'Die Audio-Datei wurde gelöscht.', $rentalId, 'dateien');
    }

    /** Vorhören im Browser (mit Range-Unterstützung für Safari/iOS). */
    public function stream(Request $request): void
    {
        $file = $this->files->findById($request->param('fileId'));
        if ($file === null || $file->rentalId !== $request->param('id')) {
            Response::notFound('Die Audio-Datei wurde nicht gefunden.');

            return;
        }
        Response::file($this->storage->absolutePath(RentalAudioFile::storageDirectory($file->rentalId), $file->storedFilename), $file->originalFilename, $file->mimeType);
    }

    public function saveSettings(Request $request): void
    {
        $rentalId = $this->requireRental($request);
        $this->run(fn () => $this->commands->dispatch(new SaveRentalAudioSettings(
            $rentalId,
            $request->input('player_id'),
            $request->input('gong_by_default') === '1',
        )), 'Die Audio-Einstellungen wurden gespeichert.', $rentalId, 'geraet');
    }

    public function createLogin(Request $request): void
    {
        $rentalId = $this->requireRental($request);
        try {
            $result = $this->commands->dispatch(new CreateOrganizerLogin(
                $rentalId,
                $request->input('label', '') ?? '',
                $request->input('username', '') ?? '',
                $request->input('password'),
                $request->input('valid_from'),
                $request->input('valid_until'),
                $request->input('can_schedule') === '1',
            ));
            $this->rememberCredentials($rentalId, $result['username'], $result['password']);
            Flash::success('Der Veranstalter-Zugang wurde angelegt.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }
        $this->back($rentalId, 'zugaenge');
    }

    public function updateLogin(Request $request): void
    {
        $rentalId = $this->requireRental($request);
        try {
            $newPassword = $this->commands->dispatch(new UpdateOrganizerLogin(
                $request->param('loginId'),
                $rentalId,
                $request->input('label', '') ?? '',
                $request->input('valid_from'),
                $request->input('valid_until'),
                $request->input('enabled') === '1',
                $request->input('reset_password') === '1',
                $request->input('can_schedule') === '1',
            ));
            if (is_string($newPassword)) {
                $login = $this->logins->findById($request->param('loginId'));
                $this->rememberCredentials($rentalId, $login?->username ?? '', $newPassword);
            }
            Flash::success(is_string($newPassword) ? 'Das Passwort wurde neu erzeugt.' : 'Der Zugang wurde gespeichert.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        $this->back($rentalId, 'zugaenge');
    }

    public function deleteLogin(Request $request): void
    {
        $rentalId = $this->requireRental($request);
        $this->run(fn () => $this->commands->dispatch(new DeleteOrganizerLogin($request->param('loginId'), $rentalId)), 'Der Zugang wurde gelöscht.', $rentalId, 'zugaenge');
    }

    public function schedule(Request $request): void
    {
        $rentalId = $this->requireRental($request);
        $user = CurrentUser::get();
        $upload = $request->file('file');
        $this->run(fn () => $this->commands->dispatch(new SchedulePlayback(
            $rentalId,
            $request->input('audio_file_id'),
            $upload !== null && (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $upload : null,
            (string) $request->input('title', ''),
            (string) $request->input('play_at', ''),
            $request->input('note'),
            'user',
            $user?->id,
            $user?->displayName() ?? 'Benutzer',
        )), 'Der Einspieler wurde geplant.', $rentalId, 'planung');
    }

    public function cancelSchedule(Request $request): void
    {
        $rentalId = $this->requireRental($request);
        $this->run(fn () => $this->commands->dispatch(new CancelScheduledPlayback($request->param('scheduleId'), $rentalId)), 'Der geplante Einspieler wurde abgebrochen.', $rentalId, 'planung');
    }

    private function rememberCredentials(string $rentalId, string $username, string $password): void
    {
        Session::ensureStarted();
        $_SESSION[self::SESSION_CREDENTIALS] = ['rental' => $rentalId, 'username' => $username, 'password' => $password];
    }

    private function requireRental(Request $request): string
    {
        $rental = $this->rentals->findById($request->param('id'));
        if ($rental === null) {
            Response::notFound('Die Veranstaltung wurde nicht gefunden.');
        }

        return $rental->id;
    }

    private function run(callable $action, string $success, string $rentalId, string $anchor): void
    {
        try {
            $action();
            Flash::success($success);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        $this->back($rentalId, $anchor);
    }

    private function back(string $rentalId, string $anchor): void
    {
        Response::redirect('/veranstaltungen/' . $rentalId . '/audio#' . $anchor);
    }
}
