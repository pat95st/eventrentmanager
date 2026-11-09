<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

interface RentalAudioFileRepositoryInterface
{
    /** @return RentalAudioFile[] */
    public function findByRental(string $rentalId, ?AudioFileKind $kind = null): array;

    public function findById(string $id): ?RentalAudioFile;

    public function save(RentalAudioFile $file): void;

    /** Löscht die Datei-Zeile und entfernt Verweise aus Minutenprogrammen. */
    public function delete(string $id): void;

    public function nextSortOrder(string $rentalId, AudioFileKind $kind): int;
}
