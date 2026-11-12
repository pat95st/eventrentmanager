<?php

declare(strict_types=1);

namespace App\Domain\Insurance;

/**
 * Ergänzende Daten der Versicherungsliste (Migration 143). Der Zeitwert
 * eines einzelnen Exemplars liegt direkt an der Inventarnummer (siehe
 * App\Domain\InventoryNumber\InventoryNumber::$currentValue); hier nur der
 * optionale feste Zeitwert je Artikel und die zuletzt gewählten Exportspalten.
 */
interface CurrentValueRepositoryInterface
{
    public function articleFixedValue(string $articleId): ?string;

    /**
     * @param string[] $articleIds
     * @return array<string, string> article_id => fester Zeitwert (nur vorhandene)
     */
    public function articleFixedValues(array $articleIds): array;

    /** null löscht den festen Wert. */
    public function setArticleFixedValue(string $articleId, ?string $value): void;

    /** @return list<InsuranceField> zuletzt gewählte Exportspalten */
    public function exportFields(): array;

    /** @param list<InsuranceField> $fields */
    public function saveExportFields(array $fields): void;
}
