<?php

declare(strict_types=1);

namespace App\Domain\Insurance;

/**
 * Zeitwerte für die Versicherungsliste (Migration 143): je Inventarnummer
 * ein eigener Wert, je Artikel optional ein fester Wert, der für alle
 * Exemplare ohne eigenen Wert gilt.
 */
interface CurrentValueRepositoryInterface
{
    public function forInventoryNumber(string $inventoryNumberId): ?CurrentValue;

    /**
     * @param string[] $inventoryNumberIds
     * @return array<string, CurrentValue> inventory_number_id => Wert (nur vorhandene)
     */
    public function forInventoryNumbers(array $inventoryNumberIds): array;

    /** null löscht den gespeicherten Wert. */
    public function setForInventoryNumber(string $inventoryNumberId, ?string $value, string $source = CurrentValue::SOURCE_MANUAL, ?string $note = null): void;

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
