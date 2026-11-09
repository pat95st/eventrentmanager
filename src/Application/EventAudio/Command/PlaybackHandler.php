<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\EventAudio\AudioFileKind;
use App\Domain\EventAudio\AudioFormat;
use App\Domain\EventAudio\AudioSettingsRepositoryInterface;
use App\Domain\EventAudio\PlaybackKind;
use App\Domain\EventAudio\PlaybackRequest;
use App\Domain\EventAudio\PlaybackRequestRepositoryInterface;
use App\Domain\EventAudio\PlaybackStatus;
use App\Domain\EventAudio\RentalAudioFileRepositoryInterface;
use App\Domain\EventAudio\RentalAudioSettingsRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Abspielaufträge (RequestPlayback, CancelPlayback, ReportPlaybackStatus).
 * Ein Stopp-Auftrag bricht zusätzlich alle noch wartenden Aufträge ab.
 */
final class PlaybackHandler implements CommandHandlerInterface
{
    public const MAX_TEXT_LENGTH = 1000;
    public const MAX_RECORDING_BYTES = 25 * 1024 * 1024;

    public function __construct(
        private readonly PlaybackRequestRepositoryInterface $requests,
        private readonly RentalAudioFileRepositoryInterface $files,
        private readonly RentalAudioSettingsRepositoryInterface $rentalSettings,
        private readonly AudioSettingsRepositoryInterface $settings,
        private readonly LocalFileStorage $storage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof RequestPlayback => $this->request($command),
            $command instanceof CancelPlayback => $this->cancel($command),
            $command instanceof ReportPlaybackStatus => $this->report($command),
            default => throw new \LogicException('Unbekannter Befehl ' . $command::class),
        };
    }

    private function request(RequestPlayback $command): string
    {
        $kind = PlaybackKind::tryFrom($command->kind) ?? throw new ValidationException(['kind' => 'Unbekannte Aktion.']);
        $id = Uuid::v4();
        $audioFileId = null;
        $text = null;
        $label = null;
        $recordingFile = null;
        $recordingMime = null;

        switch ($kind) {
            case PlaybackKind::Jingle:
            case PlaybackKind::Audio:
                $file = $command->audioFileId !== null ? $this->files->findById($command->audioFileId) : null;
                if ($file === null || $file->rentalId !== $command->rentalId) {
                    throw new ValidationException(['audio' => 'Die Audio-Datei wurde nicht gefunden.']);
                }
                $kind = $file->kind === AudioFileKind::Jingle ? PlaybackKind::Jingle : PlaybackKind::Audio;
                $audioFileId = $file->id;
                $label = $file->title;
                break;
            case PlaybackKind::Tts:
                $text = trim(preg_replace('/\s+/u', ' ', (string) $command->text) ?? '');
                if ($text === '') {
                    throw new ValidationException(['text' => 'Bitte einen Text für die Durchsage eingeben.']);
                }
                if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
                    throw new ValidationException(['text' => 'Die Durchsage darf höchstens ' . self::MAX_TEXT_LENGTH . ' Zeichen lang sein.']);
                }
                $label = mb_strimwidth($text, 0, 60, '…');
                break;
            case PlaybackKind::Recording:
                $upload = $command->recordingFile;
                if ($upload === null || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($upload['tmp_name'] ?? '') === '') {
                    throw new ValidationException(['recording' => 'Die Aufnahme ist nicht angekommen. Bitte erneut versuchen.']);
                }
                if ((int) $upload['size'] > self::MAX_RECORDING_BYTES) {
                    throw new ValidationException(['recording' => 'Die Aufnahme ist zu lang (max. 25 MB).']);
                }
                $mime = strtolower(trim(explode(';', (string) ($upload['type'] ?? ''))[0]));
                $ext = AudioFormat::RECORDING_MIME_EXTENSIONS[$mime] ?? null;
                if ($ext === null || !AudioFormat::looksLikeAudio((string) $upload['tmp_name'], $ext)) {
                    throw new ValidationException(['recording' => 'Unbekanntes Aufnahmeformat (' . ($mime !== '' ? $mime : 'leer') . ').']);
                }
                $stored = $this->storage->storeLocalFile(
                    (string) $upload['tmp_name'],
                    PlaybackRequest::recordingDirectory($command->rentalId),
                    $id,
                    'durchsage.' . $ext,
                );
                $recordingFile = $stored['storedFilename'];
                $recordingMime = str_starts_with($mime, 'video/') ? 'audio/' . substr($mime, 6) : $mime;
                $label = 'Mikrofon-Aufnahme';
                break;
            case PlaybackKind::Stop:
                $label = 'Wiedergabe stoppen';
                foreach ($this->requests->queuedForRental($command->rentalId) as $queued) {
                    $queued->status = PlaybackStatus::Cancelled;
                    $queued->finishedAt = new DateTimeImmutable();
                    $queued->error = 'Durch Stopp abgebrochen.';
                    $this->requests->save($queued);
                }
                break;
        }

        $this->requests->save(new PlaybackRequest(
            id: $id,
            rentalId: $command->rentalId,
            kind: $kind,
            audioFileId: $audioFileId,
            text: $text,
            recordingStoredFilename: $recordingFile,
            recordingMimeType: $recordingMime,
            withGong: $kind->isAnnouncement() && $command->withGong,
            status: PlaybackStatus::Queued,
            requestedByType: $command->requestedByType,
            requestedById: $command->requestedById,
            requestedByName: $command->requestedByName,
            playerId: $this->rentalSettings->get($command->rentalId)->playerId,
            error: null,
            createdAt: new DateTimeImmutable(),
            startedAt: null,
            finishedAt: null,
            label: $label,
        ));

        return $id;
    }

    private function cancel(CancelPlayback $command): null
    {
        $request = $this->requests->findById($command->requestId);
        if ($request === null || $request->rentalId !== $command->rentalId) {
            throw new NotFoundException('Auftrag nicht gefunden.');
        }
        if ($request->status === PlaybackStatus::Queued) {
            $request->status = PlaybackStatus::Cancelled;
            $request->finishedAt = new DateTimeImmutable();
            $request->error = 'Abgebrochen.';
            $this->requests->save($request);
        }

        return null;
    }

    private function report(ReportPlaybackStatus $command): null
    {
        $request = $this->requests->findById($command->requestId);
        if ($request === null) {
            throw new NotFoundException('Auftrag nicht gefunden.');
        }
        $status = PlaybackStatus::tryFrom($command->status);
        if ($status === null || $status === PlaybackStatus::Queued) {
            throw new ValidationException(['status' => 'Erlaubt: playing, done, failed, cancelled.']);
        }
        $now = new DateTimeImmutable();
        $request->status = $status;
        $request->playerId = $command->playerId;
        if ($status === PlaybackStatus::Playing) {
            $request->startedAt ??= $now;
        } else {
            $request->startedAt ??= $now;
            $request->finishedAt = $now;
        }
        $request->error = $status === PlaybackStatus::Failed ? ($command->error !== null && trim($command->error) !== '' ? trim($command->error) : 'Unbekannter Fehler') : null;
        $this->requests->save($request);

        return null;
    }
}
