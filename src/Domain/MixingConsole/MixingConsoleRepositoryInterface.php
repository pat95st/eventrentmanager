<?php

declare(strict_types=1);

namespace App\Domain\MixingConsole;

/** Mixing console details of articles (migration 154). */
interface MixingConsoleRepositoryInterface
{
    public function findByArticle(string $articleId): ?ArticleMixingConsole;

    /** Saves the console including all rows (interfaces, channels, cards, stageboxes), replacing previous rows. */
    public function save(ArticleMixingConsole $console): void;

    public function delete(string $articleId): void;

    /** @return array<string, int> protocol ID => number of consoles using it (interfaces or expansion cards) */
    public function protocolUsageCounts(): array;

    /** @return StageboxOption[] all digital stagebox articles, sorted by manufacturer and name */
    public function findStageboxOptions(): array;

    /** @return CompatibleConsole[] consoles listing the given stagebox, sorted by name */
    public function findConsolesForStagebox(string $stageboxArticleId): array;
}
