<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

use DateTimeImmutable;

interface PlaybackRequestRepositoryInterface
{
    public function save(PlaybackRequest $request): void;

    public function findById(string $id): ?PlaybackRequest;

    /** @return PlaybackRequest[] neueste zuerst */
    public function recentForRental(string $rentalId, int $limit = 20): array;

    /** @return PlaybackRequest[] offene Aufträge (wartet), älteste zuerst */
    public function queuedForRental(string $rentalId): array;

    /** Setzt wartende Aufträge, die älter als $before sind, auf abgebrochen (verwaiste Warteschlange). */
    public function expireQueuedBefore(string $rentalId, DateTimeImmutable $before): int;
}
