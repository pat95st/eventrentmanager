<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

use DateTimeImmutable;

/**
 * Ablaufplan (Minutenprogramm) einer Veranstaltung (App\Domain\Rental\Rental).
 * Eine Veranstaltung kann mehrere Minutenprogramme haben. Die tatsächliche
 * Uhrzeit der einzelnen Programmpunkte wird NICHT gespeichert, sondern aus
 * $startAt + kumulativer Dauer + $currentDelayMinutes berechnet, siehe
 * App\Application\MinuteProgram\Query\MinuteProgramDetailBuilder.
 */
final class MinuteProgram
{
    public function __construct(
        public readonly string $id,
        public string $rentalId,
        public string $name,
        public ?string $room,
        public DateTimeImmutable $startAt,
        public bool $jingleBefore,
        public int $handheldMicCount,
        public int $headsetMicCount,
        // Live pflegbarer Verzögerungswert in Minuten, auch negativ möglich.
        // Verschiebt nur die Anzeige/Live-Ansichten, nicht den ursprünglichen
        // Plan ($startAt, Dauer der Punkte) selbst.
        public int $currentDelayMinutes,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function delayedStartAt(): DateTimeImmutable
    {
        return $this->startAt->modify($this->currentDelayMinutes . ' minutes');
    }
}
