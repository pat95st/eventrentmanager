<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\DigitalStagebox\ArticleDigitalStagebox;
use App\Domain\DigitalStagebox\DigitalProtocol;
use App\Domain\DigitalStagebox\DigitalStageboxRepositoryInterface;

final class InMemoryDigitalStageboxRepository implements DigitalStageboxRepositoryInterface
{
    /** @var array<string, DigitalProtocol> */
    private array $protocols = [];

    /** @var array<string, ArticleDigitalStagebox> */
    private array $stageboxes = [];

    public function findAllProtocols(): array
    {
        $protocols = array_values($this->protocols);
        usort($protocols, static fn (DigitalProtocol $a, DigitalProtocol $b) => [$a->sortOrder, $a->name] <=> [$b->sortOrder, $b->name]);

        return $protocols;
    }

    public function findProtocolById(string $id): ?DigitalProtocol
    {
        return $this->protocols[$id] ?? null;
    }

    public function saveProtocol(DigitalProtocol $protocol): void
    {
        $this->protocols[$protocol->id] = $protocol;
    }

    public function deleteProtocol(string $id): void
    {
        unset($this->protocols[$id]);
    }

    public function protocolNameExists(string $name, ?string $excludeId = null): bool
    {
        foreach ($this->protocols as $protocol) {
            if ($protocol->id !== $excludeId && mb_strtolower($protocol->name) === mb_strtolower($name)) {
                return true;
            }
        }

        return false;
    }

    public function usageCounts(): array
    {
        $counts = [];
        foreach ($this->stageboxes as $stagebox) {
            $counts[$stagebox->protocolId] = ($counts[$stagebox->protocolId] ?? 0) + 1;
        }

        return $counts;
    }

    public function findByArticle(string $articleId): ?ArticleDigitalStagebox
    {
        return $this->stageboxes[$articleId] ?? null;
    }

    public function saveArticleStagebox(ArticleDigitalStagebox $stagebox): void
    {
        $this->stageboxes[$stagebox->articleId] = $stagebox;
    }

    public function deleteArticleStagebox(string $articleId): void
    {
        unset($this->stageboxes[$articleId]);
    }
}
