<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

/**
 * Converts the positions of an offer / order confirmation / invoice detail
 * into the raw row format the create/update commands accept (the same
 * format the web form posts). Used for responses and as current state for
 * PATCH, so positions that are not sent are kept unchanged.
 *
 * Row formats:
 *  - items:           article_id, quantity, unit_price, line_note, sort_order (+ is_rental_item on invoices)
 *  - labor_items:     labor_article_id, labor_user_id, hours, labor_billing_unit
 *  - sale_items:      article_id, source ("inventory_number" | "stock"), inventory_number_id, quantity, unit_price, line_note
 *  - free_text_items: description, quantity, unit_price, sort_order, set_id, set_upgrade_id
 *  - section_texts:   heading, sort_order
 */
final class DocumentPositions
{
    /** @return array<string, list<array<string, mixed>>> keyed by camelCase command parameter */
    public static function rows(object $detail): array
    {
        $rows = [];
        if (property_exists($detail, 'items')) {
            $rows['items'] = array_values(array_map(static function ($entry): array {
                $item = $entry->item ?? $entry;
                $row = [
                    'article_id' => $item->articleId,
                    'article_name' => isset($entry->article) ? $entry->article->name : null,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) ($item->unitPricePerDay ?? $item->unitPrice ?? '0'),
                    'line_note' => $item->lineNote ?? '',
                    'sort_order' => (string) ($item->sortOrder ?? 0),
                ];
                if (property_exists($item, 'isRentalItem')) {
                    $row['is_rental_item'] = $item->isRentalItem ? '1' : '0';
                }

                return $row;
            }, $detail->items));
        }
        if (property_exists($detail, 'laborItems')) {
            $rows['laborItems'] = array_values(array_map(static function ($entry): array {
                $item = $entry->item ?? $entry;

                return [
                    'labor_article_id' => $item->laborTimeArticleId,
                    'labor_article_name' => isset($entry->laborTimeArticle) ? $entry->laborTimeArticle->name : null,
                    'labor_user_id' => $item->userId ?? '',
                    'hours' => (string) $item->hours,
                    'labor_billing_unit' => $item->billingUnit->value,
                ];
            }, $detail->laborItems));
        }
        if (property_exists($detail, 'saleItems')) {
            $rows['saleItems'] = array_values(array_map(static function ($entry): array {
                $item = $entry->item ?? $entry;

                return [
                    'article_id' => $item->articleId,
                    'article_name' => isset($entry->article) ? $entry->article->name : null,
                    'source' => $item->inventoryNumberId !== null ? 'inventory_number' : 'stock',
                    'inventory_number_id' => $item->inventoryNumberId ?? '',
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unitPrice,
                    'line_note' => $item->lineNote ?? '',
                ];
            }, $detail->saleItems));
        }
        if (property_exists($detail, 'freeTextItems')) {
            $rows['freeTextItems'] = array_values(array_map(static function ($entry): array {
                $item = $entry->item ?? $entry;

                return [
                    'description' => $item->description,
                    'quantity' => (string) $item->quantity,
                    'unit_price' => (string) $item->unitPrice,
                    'sort_order' => (string) ($item->sortOrder ?? 0),
                    'set_id' => $item->setId ?? '',
                    'set_upgrade_id' => $item->setUpgradeId ?? '',
                ];
            }, $detail->freeTextItems));
        }
        if (property_exists($detail, 'sectionTexts')) {
            $rows['sectionTexts'] = array_values(array_map(static function ($entry): array {
                $item = $entry->item ?? $entry;

                return ['heading' => $item->heading, 'sort_order' => (string) ($item->sortOrder ?? 0)];
            }, $detail->sectionTexts));
        }

        return $rows;
    }

    /**
     * Rows for the JSON response (snake_case keys).
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function forResponse(object $detail): array
    {
        $result = [];
        foreach (self::rows($detail) as $key => $rows) {
            $result[Normalizer::snake($key)] = $rows;
        }

        return $result;
    }
}
