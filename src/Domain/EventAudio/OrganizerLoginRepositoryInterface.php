<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

interface OrganizerLoginRepositoryInterface
{
    /** @return OrganizerLogin[] */
    public function findByRental(string $rentalId): array;

    public function findById(string $id): ?OrganizerLogin;

    public function findByUsername(string $username): ?OrganizerLogin;

    public function save(OrganizerLogin $login): void;

    public function delete(string $id): void;
}
