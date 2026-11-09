<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

use DateTimeImmutable;

interface AudioPlayerRepositoryInterface
{
    /** @return AudioPlayer[] */
    public function findAll(): array;

    public function findById(string $id): ?AudioPlayer;

    public function findByTokenHash(string $hash): ?AudioPlayer;

    public function save(AudioPlayer $player): void;

    public function delete(string $id): void;

    public function touch(string $id, DateTimeImmutable $at, ?string $ip, ?string $version, ?string $nowPlaying): void;
}
