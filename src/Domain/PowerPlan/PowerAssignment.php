<?php

declare(strict_types=1);

namespace App\Domain\PowerPlan;

/**
 * Where one planned unit (article + running number within the planned
 * quantity) is plugged in during an event (migration 159): either an outlet
 * of a planned power distributor unit or a power connection of the venue.
 *
 * $phase selects the phase when it does not follow from the outlet, e.g. a
 * single-phase device on a CEE red outlet via adapter or an outlet whose
 * phase is not configured. $simultaneityFactor overrides the event-wide
 * factor for this unit.
 */
final class PowerAssignment
{
    public function __construct(
        public readonly string $articleId,
        public readonly int $unitNo,
        public readonly ?string $targetArticleId = null,
        public readonly ?int $targetUnitNo = null,
        public readonly ?string $outletId = null,
        public readonly ?string $venueConnectionId = null,
        public readonly ?string $phase = null,
        public readonly ?float $simultaneityFactor = null,
    ) {
    }

    public function key(): string
    {
        return PowerNode::keyOf($this->articleId, $this->unitNo);
    }

    public function isOutlet(): bool
    {
        return $this->targetArticleId !== null && $this->targetUnitNo !== null && $this->outletId !== null;
    }

    public function isVenue(): bool
    {
        return !$this->isOutlet() && $this->venueConnectionId !== null;
    }

    /** Key of the distributor unit this unit is plugged into (null for venue connections). */
    public function targetKey(): ?string
    {
        return $this->isOutlet() ? PowerNode::keyOf((string) $this->targetArticleId, (int) $this->targetUnitNo) : null;
    }

    /** Copy plugged into another outlet (keeps the simultaneity factor). */
    public function movedTo(string $targetArticleId, int $targetUnitNo, string $outletId, ?string $phase): self
    {
        return new self($this->articleId, $this->unitNo, $targetArticleId, $targetUnitNo, $outletId, null, $phase, $this->simultaneityFactor);
    }
}
