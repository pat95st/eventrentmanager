<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Domain\MinuteProgram\MinuteProgramItem;
use DateTimeImmutable;

final class MinuteProgramItemDetail
{
    public function __construct(
        public readonly MinuteProgramItem $item,
        public readonly DateTimeImmutable $plannedStart, // ohne Verzögerung
        public readonly DateTimeImmutable $currentStart,  // mit aktueller Verzögerung
        public readonly bool $isCurrent,
        public readonly bool $isNext,
    ) {
    }
}
