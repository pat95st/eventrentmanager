<?php

declare(strict_types=1);

namespace App\Domain\Routing;

/** Calculated route: one leg per pair of consecutive points. */
final class RouteResult
{
    /** @param RouteLeg[] $legs */
    public function __construct(
        public readonly array $legs,
        public readonly bool $straightLine,
    ) {
    }

    /** @param RoutePoint[] $points */
    public static function straightLine(array $points): self
    {
        $legs = [];
        for ($i = 1, $n = count($points); $i < $n; $i++) {
            $a = $points[$i - 1];
            $b = $points[$i];
            $legs[] = new RouteLeg($i - 1, $i, $a->distanceTo($b), null, [[$a->latitude, $a->longitude], [$b->latitude, $b->longitude]]);
        }

        return new self($legs, true);
    }

    public function totalDistanceM(): float
    {
        return array_sum(array_map(static fn (RouteLeg $l) => $l->distanceM, $this->legs));
    }

    public function totalDurationS(): ?float
    {
        if ($this->straightLine) {
            return null;
        }

        return array_sum(array_map(static fn (RouteLeg $l) => (float) $l->durationS, $this->legs));
    }

    /** @return array<string, mixed> for the route cache */
    public function toArray(): array
    {
        return [
            'straightLine' => $this->straightLine,
            'legs' => array_map(static fn (RouteLeg $l) => [$l->from, $l->to, $l->distanceM, $l->durationS, $l->geometry], $this->legs),
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $legs = [];
        foreach (is_array($data['legs'] ?? null) ? $data['legs'] : [] as $leg) {
            $legs[] = new RouteLeg((int) $leg[0], (int) $leg[1], (float) $leg[2], $leg[3] !== null ? (float) $leg[3] : null, $leg[4]);
        }

        return new self($legs, (bool) ($data['straightLine'] ?? false));
    }
}
