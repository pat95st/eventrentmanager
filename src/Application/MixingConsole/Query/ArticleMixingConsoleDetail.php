<?php

declare(strict_types=1);

namespace App\Application\MixingConsole\Query;

use App\Domain\DigitalStagebox\DigitalProtocol;
use App\Domain\MixingConsole\ArticleMixingConsole;
use App\Domain\MixingConsole\StageboxCompatibility;
use App\Domain\MixingConsole\StageboxOption;

final class ArticleMixingConsoleDetail
{
    /**
     * @param array<string, DigitalProtocol> $protocols protocol ID => protocol (all protocols)
     * @param array<string, StageboxOption> $stageboxOptions stagebox article ID => stagebox (all digital stageboxes)
     */
    public function __construct(
        public readonly ArticleMixingConsole $console,
        public readonly array $protocols,
        public readonly array $stageboxOptions,
    ) {
    }

    public function protocolName(?string $id): string
    {
        return $id !== null ? ($this->protocols[$id]->name ?? '–') : 'analog';
    }

    /** @return StageboxOption[] linked stageboxes with the given compatibility that still exist */
    public function stageboxes(StageboxCompatibility $compatibility): array
    {
        return array_values(array_filter(array_map(
            fn (string $id) => $this->stageboxOptions[$id] ?? null,
            $this->console->stageboxIds($compatibility),
        )));
    }
}
