<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\MixingConsole\ArticleMixingConsole;
use App\Domain\MixingConsole\MixingConsoleRepositoryInterface;
use App\Domain\MixingConsole\StageboxOption;

final class InMemoryMixingConsoleRepository implements MixingConsoleRepositoryInterface
{
    /** @var array<string, ArticleMixingConsole> */
    private array $consoles = [];

    /** @var array<string, StageboxOption> */
    private array $stageboxOptions = [];

    public function addStageboxOption(StageboxOption $option): void
    {
        $this->stageboxOptions[$option->articleId] = $option;
    }

    public function findByArticle(string $articleId): ?ArticleMixingConsole
    {
        return $this->consoles[$articleId] ?? null;
    }

    public function save(ArticleMixingConsole $console): void
    {
        $this->consoles[$console->articleId] = $console;
    }

    public function delete(string $articleId): void
    {
        unset($this->consoles[$articleId]);
    }

    public function protocolUsageCounts(): array
    {
        $counts = [];
        foreach ($this->consoles as $console) {
            foreach ($console->protocolIds() as $protocolId) {
                $counts[$protocolId] = ($counts[$protocolId] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function findStageboxOptions(): array
    {
        return array_values($this->stageboxOptions);
    }

    public function findConsolesForStagebox(string $stageboxArticleId): array
    {
        return [];
    }
}
