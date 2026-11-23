<?php

declare(strict_types=1);

namespace App\Domain\TravelCost;

use DateTimeImmutable;

/**
 * One billed trip of a tour: from a stop (or the start) to the next stop
 * (or the destination); waypoints in between are part of the trip. The
 * kilometres are whole kilometres (rounded per trip).
 */
final class TravelCostTrip
{
    public function __construct(
        public readonly ?DateTimeImmutable $date,
        public readonly string $from,
        public readonly string $to,
        public readonly int $kilometers,
        // Migration 187: own price per km (logbook trips: vehicle + trailer + markup) and vehicle label.
        public readonly ?string $ratePerKm = null,
        public readonly ?string $vehicle = null,
    ) {
    }

    /** @return array{date: ?string, from: string, to: string, km: int, rate?: string, vehicle?: string} */
    public function toArray(): array
    {
        return ['date' => $this->date?->format('Y-m-d'), 'from' => $this->from, 'to' => $this->to, 'km' => $this->kilometers]
            + ($this->ratePerKm !== null ? ['rate' => $this->ratePerKm] : [])
            + ($this->vehicle !== null ? ['vehicle' => $this->vehicle] : []);
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $date = isset($data['date']) && is_string($data['date']) && $data['date'] !== '' ? new DateTimeImmutable($data['date']) : null;
        $from = $data['from'] ?? '';
        $to = $data['to'] ?? '';
        $km = $data['km'] ?? 0;

        $rate = $data['rate'] ?? null;
        $vehicle = $data['vehicle'] ?? null;

        return new self(
            $date,
            is_scalar($from) ? (string) $from : '',
            is_scalar($to) ? (string) $to : '',
            is_numeric($km) ? (int) $km : 0,
            is_numeric($rate) ? number_format((float) $rate, 2, '.', '') : null,
            is_string($vehicle) && $vehicle !== '' ? $vehicle : null,
        );
    }
}
