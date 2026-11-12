<?php

declare(strict_types=1);

namespace App\Domain\RevenueGoal;

/** Stand eines Umsatzziels in einem Jahr: Ist-Umsatz gegenüber dem für das Jahr geltenden Betrag. */
final class RevenueGoalProgress
{
    public const STATUS_OPEN = 'open';         // Ziel noch nicht erreicht
    public const STATUS_REACHED = 'reached';   // Ziel erreicht
    public const STATUS_OK = 'ok';             // Grenze eingehalten, noch Luft
    public const STATUS_WARNING = 'warning';   // Grenze zu mindestens 80 % ausgeschöpft
    public const STATUS_EXCEEDED = 'exceeded'; // Grenze überschritten

    private const WARNING_RATIO = 0.8;

    public function __construct(
        public readonly RevenueGoal $goal,
        public readonly int $year,
        public readonly float $actual,
    ) {
    }

    public function target(): float
    {
        return (float) $this->goal->amountFor($this->year);
    }

    /** Anteil des Ist-Umsatzes am Betrag in Prozent (kann über 100 liegen); 0 bei Betrag 0. */
    public function percent(): float
    {
        $target = $this->target();

        return $target > 0 ? round($this->actual / $target * 100, 1) : 0.0;
    }

    /** Positiv: so viel fehlt noch bis zum Ziel bzw. ist bis zur Grenze noch frei. Negativ: so viel liegt der Umsatz darüber. */
    public function remaining(): float
    {
        return round($this->target() - $this->actual, 2);
    }

    public function status(): string
    {
        $target = $this->target();
        if ($this->goal->kind === RevenueGoalKind::Target) {
            return $this->actual >= $target ? self::STATUS_REACHED : self::STATUS_OPEN;
        }
        if ($this->actual > $target) {
            return self::STATUS_EXCEEDED;
        }

        return $this->actual >= $target * self::WARNING_RATIO ? self::STATUS_WARNING : self::STATUS_OK;
    }
}
