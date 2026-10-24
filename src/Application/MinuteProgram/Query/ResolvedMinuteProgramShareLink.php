<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Domain\MinuteProgram\MinuteProgramShareLink;

final class ResolvedMinuteProgramShareLink
{
    public function __construct(
        public readonly MinuteProgramShareLink $link,
        public readonly MinuteProgramDetail $detail,
    ) {
    }
}
