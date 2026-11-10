<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\EventAudio\AudioFileKind;
use App\Domain\EventAudio\RentalAudioFileRepositoryInterface;
use App\Domain\EventAudio\ScheduledPlayback;
use App\Domain\EventAudio\ScheduledPlaybackRepositoryInterface;
use App\Domain\EventAudio\ScheduledPlaybackStatus;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Geplante Einspieler anlegen/abbrechen (Migration 136). Ausgelöst werden sie
 * vom ScheduledPlaybackDispatcher, sobald die Startzeit erreicht ist.
 */
final class ScheduledPlaybackHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ScheduledPlaybackRepositoryInterface $schedules,
        private readonly RentalAudioFileRepositoryInterface $files,
        private readonly RentalAudioFileHandler $fileHandler,
        private readonly ?RentalRepositoryInterface $rentals = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof SchedulePlayback => $this->schedule($command),
            $command instanceof CancelScheduledPlayback => $this->cancel($command),
            default => throw new \LogicException('Unbekannter Befehl ' . $command::class),
        };
    }

    private function schedule(SchedulePlayback $command): string
    {
        $playAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', trim($command->playAt))
            ?: DateTimeImmutable::createFromFormat('!Y-m-d H:i', trim($command->playAt));
        if ($playAt === false) {
            throw new ValidationException(['play_at' => 'Bitte Datum und Uhrzeit für den Einspieler angeben.']);
        }
        $now = new DateTimeImmutable();
        if ($playAt < $now->modify('-1 minute')) {
            throw new ValidationException(['play_at' => 'Der Zeitpunkt liegt in der Vergangenheit.']);
        }
        $rental = $this->rentals?->findById($command->rentalId);
        if ($rental !== null) {
            $windowStart = $rental->startDate->modify('-1 day')->setTime(0, 0);
            $windowEnd = $rental->endDate->modify('+1 day')->setTime(23, 59, 59);
            if ($playAt < $windowStart || $playAt > $windowEnd) {
                throw new ValidationException(['play_at' => 'Der Zeitpunkt muss zwischen ' . $windowStart->format('d.m.Y') . ' und ' . $windowEnd->format('d.m.Y') . ' liegen (Zeitraum der Veranstaltung).']);
            }
        }

        $hasUpload = is_array($command->uploadedFile) && (int) ($command->uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasUpload) {
            $audioFileId = (string) $this->fileHandler->handle(new UploadRentalAudioFile(
                $command->rentalId,
                AudioFileKind::Audio->value,
                $command->uploadTitle,
                'Für geplanten Einspieler hochgeladen von ' . $command->createdByName,
                $command->uploadedFile,
            ));
        } else {
            $file = $command->audioFileId !== null && $command->audioFileId !== '' ? $this->files->findById($command->audioFileId) : null;
            if ($file === null || $file->rentalId !== $command->rentalId) {
                throw new ValidationException(['audio' => 'Bitte einen Jingle bzw. eine Audio-Datei wählen oder eine Datei hochladen.']);
            }
            $audioFileId = $file->id;
        }

        $note = trim((string) $command->note);
        $id = Uuid::v4();
        $this->schedules->save(new ScheduledPlayback(
            id: $id,
            rentalId: $command->rentalId,
            audioFileId: $audioFileId,
            playAt: $playAt,
            note: $note !== '' ? mb_substr($note, 0, 255) : null,
            status: ScheduledPlaybackStatus::Pending,
            playbackRequestId: null,
            createdByType: $command->createdByType === 'organizer' ? 'organizer' : 'user',
            createdById: $command->createdById,
            createdByName: mb_substr($command->createdByName, 0, 160),
            createdAt: $now,
        ));

        return $id;
    }

    private function cancel(CancelScheduledPlayback $command): null
    {
        $schedule = $this->schedules->findById($command->id);
        if ($schedule === null || $schedule->rentalId !== $command->rentalId) {
            throw new NotFoundException('Der geplante Einspieler wurde nicht gefunden.');
        }
        if (!$this->schedules->claim($schedule->id, ScheduledPlaybackStatus::Cancelled, new DateTimeImmutable())) {
            throw new NotFoundException('Der Einspieler wurde bereits ausgelöst und kann nicht mehr abgebrochen werden.');
        }

        return null;
    }
}
