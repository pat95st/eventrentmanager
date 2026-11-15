<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Cable\ArticleCable;
use App\Domain\Cable\CableRepositoryInterface;
use App\Domain\Cable\CableType;
use App\Domain\Cable\ConnectorType;

final class InMemoryCableRepository implements CableRepositoryInterface
{
    /** @var array<string, CableType> */
    private array $types = [];

    /** @var array<string, ConnectorType> */
    private array $connectors = [];

    /** @var array<string, ArticleCable> */
    private array $cables = [];

    public function findAllTypes(): array
    {
        return array_values($this->types);
    }

    public function findTypeById(string $id): ?CableType
    {
        return isset($this->types[$id]) ? clone $this->types[$id] : null;
    }

    public function saveType(CableType $type): void
    {
        $this->types[$type->id] = clone $type;
    }

    public function deleteType(string $id): void
    {
        unset($this->types[$id]);
    }

    public function typeNameExists(string $name, ?string $excludeId = null): bool
    {
        foreach ($this->types as $type) {
            if ($type->name === $name && $type->id !== $excludeId) {
                return true;
            }
        }

        return false;
    }

    public function findAllConnectors(): array
    {
        return array_values($this->connectors);
    }

    public function findConnectorById(string $id): ?ConnectorType
    {
        return $this->connectors[$id] ?? null;
    }

    public function saveConnector(ConnectorType $connector): void
    {
        $this->connectors[$connector->id] = $connector;
    }

    public function deleteConnector(string $id): void
    {
        unset($this->connectors[$id]);
    }

    public function connectorNameExists(string $name, ?string $excludeId = null): bool
    {
        foreach ($this->connectors as $connector) {
            if ($connector->name === $name && $connector->id !== $excludeId) {
                return true;
            }
        }

        return false;
    }

    public function usageCounts(): array
    {
        $counts = [];
        foreach ($this->cables as $cable) {
            foreach ([$cable->cableTypeId, $cable->connectorAId, $cable->connectorBId] as $id) {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function findByArticle(string $articleId): ?ArticleCable
    {
        return $this->cables[$articleId] ?? null;
    }

    public function saveArticleCable(ArticleCable $cable): void
    {
        $this->cables[$cable->articleId] = $cable;
    }

    public function deleteArticleCable(string $articleId): void
    {
        unset($this->cables[$articleId]);
    }
}
