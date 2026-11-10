<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\EventAudio\Command\ReportPlaybackStatus;
use App\Application\EventAudio\ScheduledPlaybackDispatcher;
use App\Domain\EventAudio\AudioPlayer;
use App\Domain\EventAudio\AudioPlayerRepositoryInterface;
use App\Domain\EventAudio\AudioSettings;
use App\Domain\EventAudio\AudioSettingsRepositoryInterface;
use App\Domain\EventAudio\PlaybackRequest;
use App\Domain\EventAudio\PlaybackRequestRepositoryInterface;
use App\Domain\EventAudio\PlaybackStatus;
use App\Domain\EventAudio\RentalAudioFile;
use App\Domain\EventAudio\RentalAudioFileRepositoryInterface;
use App\Domain\EventAudio\RentalAudioSettingsRepositoryInterface;
use App\Domain\EventAudio\ScheduledPlaybackRepositoryInterface;
use App\Domain\EventAudio\ScheduledPlaybackStatus;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use DateTimeImmutable;

/**
 * Schnittstelle für das Abspielgerät (Raspberry Pi am Mischpult), Migration 135.
 *
 * Authentifizierung: "Authorization: Bearer <Geräte-Token>" (Einstellungen ›
 * Veranstaltungs-Audio). Das Gerät fragt GET /api/player/v1/state alle paar
 * Sekunden ab (zugleich Lebenszeichen), lädt neue/geänderte Dateien herunter,
 * spielt wartende Aufträge der Reihe nach ab und meldet den Status zurück.
 * Ausführliche Beschreibung: docs/abspielgeraet-api.md (auch im Projekt als claude/abspielgeraet-api.md).
 */
final class AudioPlayerApiController
{
    public const POLL_INTERVAL_SECONDS = 2;

    public function __construct(
        private readonly CommandBus $commands,
        private readonly AudioPlayerRepositoryInterface $players,
        private readonly AudioSettingsRepositoryInterface $settings,
        private readonly RentalAudioSettingsRepositoryInterface $rentalSettings,
        private readonly RentalAudioFileRepositoryInterface $files,
        private readonly PlaybackRequestRepositoryInterface $requests,
        private readonly RentalRepositoryInterface $rentals,
        private readonly LocalFileStorage $storage,
        private readonly ?MinuteProgramRepositoryInterface $minutePrograms = null,
        private readonly ?ScheduledPlaybackRepositoryInterface $schedules = null,
        private readonly ?ScheduledPlaybackDispatcher $dispatcher = null,
    ) {
    }

    public function state(Request $request): void
    {
        $player = $this->authenticate();
        $now = new DateTimeImmutable();
        $nowPlaying = $request->query('playing');
        $this->players->touch(
            $player->id,
            $now,
            $this->clientIp(),
            $this->limit($request->query('version'), 60),
            $nowPlaying !== null && trim($nowPlaying) !== '' ? $this->limit($nowPlaying, 255) : null,
        );

        $settings = $this->settings->get();
        $rentalId = $this->rentalSettings->activeRentalIdForPlayer($player->id, $now);
        $rental = $rentalId !== null ? $this->rentals->findById($rentalId) : null;
        $base = '/api/player/v1';

        $payload = [
            'server_time' => $now->format(DATE_ATOM),
            'poll_interval_seconds' => self::POLL_INTERVAL_SECONDS,
            'player' => ['id' => $player->id, 'name' => $player->name],
            'tts' => ['language' => $settings->ttsLanguage, 'voice' => $settings->ttsVoice, 'rate' => $settings->ttsRate],
            'gong' => $settings->gongStoredFilename === null ? null : [
                'url' => $base . '/gong',
                'version' => $settings->gongStoredFilename,
                'mime' => $settings->gongMimeType,
            ],
            'rental' => null,
            'files' => [],
            'minute_programs' => [],
            'scheduled' => [],
            'queue' => [],
        ];

        if ($rental !== null) {
            $this->dispatcher?->dispatchDue($rental->id, $now, max(1, $settings->queueExpiryMinutes));
            $this->requests->expireQueuedBefore($rental->id, $now->modify('-' . max(1, $settings->queueExpiryMinutes) . ' minutes'));
            $payload['rental'] = [
                'id' => $rental->id,
                'name' => $rental->eventName,
                'location' => $rental->eventLocation,
                'start_date' => $rental->startDate->format('Y-m-d'),
                'end_date' => $rental->endDate->format('Y-m-d'),
                'gong_by_default' => $this->rentalSettings->get($rental->id)->gongByDefault,
                'tts_languages' => $this->rentalSettings->get($rental->id)->ttsLanguages,
            ];
            $payload['files'] = array_map(static fn (RentalAudioFile $f): array => [
                'id' => $f->id,
                'kind' => $f->kind->value,
                'title' => $f->title,
                'description' => $f->description,
                'filename' => $f->originalFilename,
                'mime' => $f->mimeType,
                'size' => $f->sizeBytes,
                'version' => $f->storedFilename,
                'url' => $base . '/files/' . $f->id,
            ], $this->files->findByRental($rental->id));
            foreach ($this->minutePrograms?->findByRental($rental->id) ?? [] as $program) {
                $payload['minute_programs'][] = [
                    'id' => $program->id,
                    'name' => $program->name,
                    'start_at' => $program->startAt->modify(($program->currentDelayMinutes >= 0 ? '+' : '') . $program->currentDelayMinutes . ' minutes')->format(DATE_ATOM),
                    'jingle_before' => $program->jingleBefore,
                    'jingle_file_id' => $program->jingleBefore ? $program->jingleAudioFileId : null,
                ];
            }
            foreach ($this->schedules?->findForRental($rental->id, 0) ?? [] as $s) {
                if ($s->status === ScheduledPlaybackStatus::Pending) {
                    $payload['scheduled'][] = [
                        'id' => $s->id,
                        'file_id' => $s->audioFileId,
                        'play_at' => $s->playAt->format(DATE_ATOM),
                        'note' => $s->note,
                        'repeat_interval_minutes' => $s->repeatIntervalMinutes,
                        'repeat_until' => $s->repeatUntil?->format(DATE_ATOM),
                        'repeat_max' => $s->repeatMax,
                        'repeat_count' => $s->repeatCount,
                    ];
                }
            }
            $payload['queue'] = array_map(fn (PlaybackRequest $r): array => $this->queueItem($r, $base), $this->requests->queuedForRental($rental->id));
        }

        Response::json($payload);
    }

    public function reportStatus(Request $request): void
    {
        $player = $this->authenticate();
        $playbackRequest = $this->requests->findById($request->param('id'));
        if ($playbackRequest === null || !$this->belongsToPlayer($playbackRequest->rentalId, $player)) {
            Response::json(['error' => 'Auftrag nicht gefunden.'], 404);

            return;
        }
        $body = $this->jsonBody();
        try {
            $this->commands->dispatch(new ReportPlaybackStatus(
                $player->id,
                $playbackRequest->id,
                (string) ($body['status'] ?? $request->input('status', '')),
                isset($body['error']) ? (string) $body['error'] : $request->input('error'),
            ));
        } catch (ValidationException $e) {
            Response::json(['error' => implode(' ', $e->errors())], 422);

            return;
        } catch (NotFoundException $e) {
            Response::json(['error' => $e->getMessage()], 404);

            return;
        }
        Response::json(['ok' => true]);
    }

    public function file(Request $request): void
    {
        $player = $this->authenticate();
        $file = $this->files->findById($request->param('id'));
        if ($file === null || !$this->belongsToPlayer($file->rentalId, $player)) {
            Response::json(['error' => 'Datei nicht gefunden.'], 404);

            return;
        }
        Response::file($this->storage->absolutePath(RentalAudioFile::storageDirectory($file->rentalId), $file->storedFilename), $file->originalFilename, $file->mimeType);
    }

    public function recording(Request $request): void
    {
        $player = $this->authenticate();
        $playbackRequest = $this->requests->findById($request->param('id'));
        if ($playbackRequest === null || $playbackRequest->recordingStoredFilename === null || !$this->belongsToPlayer($playbackRequest->rentalId, $player)) {
            Response::json(['error' => 'Aufnahme nicht gefunden.'], 404);

            return;
        }
        Response::file(
            $this->storage->absolutePath(PlaybackRequest::recordingDirectory($playbackRequest->rentalId), $playbackRequest->recordingStoredFilename),
            $playbackRequest->recordingStoredFilename,
            (string) $playbackRequest->recordingMimeType,
        );
    }

    public function gong(Request $request): void
    {
        $this->authenticate();
        $settings = $this->settings->get();
        if ($settings->gongStoredFilename === null) {
            Response::json(['error' => 'Kein eigener Gong hinterlegt – bitte den eingebauten Gong verwenden.'], 404);

            return;
        }
        Response::file($this->storage->absolutePath(AudioSettings::GONG_DIRECTORY, $settings->gongStoredFilename), (string) $settings->gongOriginalFilename, (string) $settings->gongMimeType);
    }

    /** @return array<string, mixed> */
    private function queueItem(PlaybackRequest $r, string $base): array
    {
        return [
            'id' => $r->id,
            'kind' => $r->kind->value,
            'label' => $r->label,
            'file_id' => $r->audioFileId,
            'file_url' => $r->audioFileId !== null ? $base . '/files/' . $r->audioFileId : null,
            'text' => $r->text,
            'language' => $r->text !== null ? ($r->language ?? $this->settings->get()->ttsLanguage) : null,
            'recording_url' => $r->recordingStoredFilename !== null ? $base . '/requests/' . $r->id . '/recording' : null,
            'recording_mime' => $r->recordingMimeType,
            'with_gong' => $r->withGong,
            'requested_by' => $r->requestedByName,
            'created_at' => $r->createdAt->format(DATE_ATOM),
            'status_url' => $base . '/requests/' . $r->id . '/status',
        ];
    }

    private function belongsToPlayer(string $rentalId, AudioPlayer $player): bool
    {
        return $this->rentalSettings->get($rentalId)->playerId === $player->id;
    }

    private function authenticate(): AudioPlayer
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    $header = (string) $value;
                }
            }
        }
        $token = preg_match('/^\s*Bearer\s+(\S+)\s*$/i', (string) $header, $m) === 1 ? $m[1] : '';
        $player = $token !== '' ? $this->players->findByTokenHash(hash('sha256', $token)) : null;
        if ($player === null || !$player->enabled) {
            header('WWW-Authenticate: Bearer');
            Response::json(['error' => 'Ungültiger oder deaktivierter Geräte-Token.'], 401);
        }

        return $player;
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        if (!str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
            return [];
        }
        $data = json_decode((string) file_get_contents('php://input'), true);

        return is_array($data) ? $data : [];
    }

    private function clientIp(): ?string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return $ip !== '' ? substr($ip, 0, 45) : null;
    }

    private function limit(?string $value, int $max): ?string
    {
        $value = $value !== null ? trim($value) : '';

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
