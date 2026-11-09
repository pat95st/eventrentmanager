<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\EventAudio\AudioFileKind;
use App\Domain\EventAudio\AudioFormat;
use App\Domain\EventAudio\AudioSettingsRepositoryInterface;
use App\Domain\EventAudio\RentalAudioFile;
use App\Domain\EventAudio\RentalAudioFileRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Use Cases rund um Jingles/Audio-Dateien einer Veranstaltung
 * (UploadRentalAudioFile, UpdateRentalAudioFile, DeleteRentalAudioFile).
 */
final class RentalAudioFileHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalAudioFileRepositoryInterface $files,
        private readonly AudioSettingsRepositoryInterface $settings,
        private readonly LocalFileStorage $storage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof UploadRentalAudioFile => $this->upload($command),
            $command instanceof UpdateRentalAudioFile => $this->update($command),
            $command instanceof DeleteRentalAudioFile => $this->delete($command),
            default => throw new \LogicException('Unbekannter Befehl ' . $command::class),
        };
    }

    private function upload(UploadRentalAudioFile $command): string
    {
        $kind = AudioFileKind::tryFrom($command->kind) ?? AudioFileKind::Audio;
        $file = $command->uploadedFile;
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new ValidationException(['file' => 'Die Datei ist größer als vom Server erlaubt (PHP upload_max_filesize/post_max_size).']);
        }
        if ($error !== UPLOAD_ERR_OK || ($file['tmp_name'] ?? '') === '') {
            throw new ValidationException(['file' => 'Bitte eine Audio-Datei auswählen.']);
        }
        $originalName = basename((string) ($file['name'] ?? 'audio'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mime = AudioFormat::uploadMimeFor($originalName);
        if ($mime === null) {
            throw new ValidationException(['file' => '„' . $originalName . '“: Erlaubt sind WAV, FLAC, AAC (auch .m4a) und MP3.']);
        }
        if (!AudioFormat::looksLikeAudio((string) $file['tmp_name'], $extension)) {
            throw new ValidationException(['file' => '„' . $originalName . '“ scheint keine gültige ' . strtoupper($extension) . '-Datei zu sein.']);
        }
        $maxMb = max(1, $this->settings->get()->maxUploadMb);
        if ((int) $file['size'] > $maxMb * 1024 * 1024) {
            throw new ValidationException(['file' => '„' . $originalName . '“ ist größer als ' . $maxMb . ' MB.']);
        }

        $title = trim($command->title);
        if ($title === '') {
            $title = pathinfo($originalName, PATHINFO_FILENAME);
        }
        $id = Uuid::v4();
        $stored = $this->storage->storeUploadedFile($file, RentalAudioFile::storageDirectory($command->rentalId), $id);

        $this->files->save(new RentalAudioFile(
            id: $id,
            rentalId: $command->rentalId,
            kind: $kind,
            title: mb_substr($title, 0, 160),
            description: $this->clean($command->description, 500),
            originalFilename: $stored['originalFilename'],
            storedFilename: $stored['storedFilename'],
            mimeType: $mime,
            sizeBytes: (int) $stored['sizeBytes'],
            sortOrder: $this->files->nextSortOrder($command->rentalId, $kind),
            createdAt: new DateTimeImmutable(),
        ));

        return $id;
    }

    private function update(UpdateRentalAudioFile $command): null
    {
        $file = $this->files->findById($command->id);
        if ($file === null || $file->rentalId !== $command->rentalId) {
            throw new NotFoundException('Audio-Datei nicht gefunden.');
        }
        $title = trim($command->title);
        if ($title === '') {
            throw new ValidationException(['title' => 'Bitte einen Titel angeben.']);
        }
        $file->title = mb_substr($title, 0, 160);
        $file->description = $this->clean($command->description, 500);
        $kind = AudioFileKind::tryFrom($command->kind);
        if ($kind !== null && $kind !== $file->kind) {
            $file->kind = $kind;
            $file->sortOrder = $this->files->nextSortOrder($file->rentalId, $kind);
        }
        $this->files->save($file);

        return null;
    }

    private function delete(DeleteRentalAudioFile $command): null
    {
        $file = $this->files->findById($command->id);
        if ($file === null || $file->rentalId !== $command->rentalId) {
            throw new NotFoundException('Audio-Datei nicht gefunden.');
        }
        $this->files->delete($file->id);
        try {
            $this->storage->delete(RentalAudioFile::storageDirectory($file->rentalId), $file->storedFilename);
        } catch (\Throwable) {
            // Datei schon weg — Datensatz ist trotzdem entfernt.
        }

        return null;
    }

    private function clean(?string $value, int $max): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
