<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Domain\MinuteProgram\MinuteProgram;

final class MinuteProgramDetail
{
    /** @param MinuteProgramItemDetail[] $items */
    public function __construct(
        public readonly MinuteProgram $program,
        public readonly array $items,
    ) {
    }
}
