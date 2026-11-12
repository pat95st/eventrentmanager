<?php

declare(strict_types=1);

namespace App\Domain\Insurance;

use DateTimeImmutable;

/** Gespeicherter Zeitwert eines einzelnen Exemplars (Inventarnummer). */
final class CurrentValue
{
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_AI = 'ai';

    public function __construct(
        public readonly string $inventoryNumberId,
        public readonly string $value, // Euro als String (DECIMAL), z. B. "123.45"
        public readonly string $source, // self::SOURCE_MANUAL | self::SOURCE_AI
        public readonly ?string $note, // z. B. Begründung der KI-Schätzung
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }

    public function isAiEstimate(): bool
    {
        return $this->source === self::SOURCE_AI;
    }
}
