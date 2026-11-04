<?php

declare(strict_types=1);

namespace App\Domain\Cable;

/** Leitungstypen, Steckverbindertypen und Leitungsangaben der Artikel (Migration 125). */
interface CableRepositoryInterface
{
    /** @return CableType[] sortiert */
    public function findAllTypes(): array;

    public function findTypeById(string $id): ?CableType;

    public function saveType(CableType $type): void;

    public function deleteType(string $id): void;

    public function typeNameExists(string $name, ?string $excludeId = null): bool;

    /** @return ConnectorType[] sortiert */
    public function findAllConnectors(): array;

    public function findConnectorById(string $id): ?ConnectorType;

    public function saveConnector(ConnectorType $connector): void;

    public function deleteConnector(string $id): void;

    public function connectorNameExists(string $name, ?string $excludeId = null): bool;

    /** @return array<string, int> Leitungstyp-ID bzw. Steckverbinder-ID => Anzahl Artikel */
    public function usageCounts(): array;

    public function findByArticle(string $articleId): ?ArticleCable;

    public function saveArticleCable(ArticleCable $cable): void;

    public function deleteArticleCable(string $articleId): void;
}
