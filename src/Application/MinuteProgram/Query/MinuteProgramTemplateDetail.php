<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Query;

use App\Domain\MinuteProgram\MinuteProgramTemplate;

final class MinuteProgramTemplateDetail
{
    /**
     * @param \App\Domain\MinuteProgram\MinuteProgramTemplateItem[] $items
     * @param list<MinuteProgramTrade> $trades
     */
    public function __construct(
        public readonly MinuteProgramTemplate $template,
        public readonly array $items,
        // Migration 172: trades the template offers notes for.
        public readonly array $trades = [],
    ) {
    }
}
