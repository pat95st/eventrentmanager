<?php

declare(strict_types=1);

namespace App\Infrastructure\Mcp;

use App\Infrastructure\Http\Api\OpenApiSchemaGenerator;
use RuntimeException;
use stdClass;

/**
 * JSON Schemas for the MCP tool arguments.
 *
 * Write tools take the same request bodies as the REST API, so their schemas
 * are derived from the command classes via OpenApiSchemaGenerator::inputs()
 * (converted from OpenAPI 3.0 to JSON Schema: "nullable" becomes a type
 * union, references to the row schemas of docs/openapi/components/common.yaml
 * are inlined from rows()). That keeps tool arguments and commands in sync.
 */
final class McpSchemas
{
    /** @var array<string, mixed>|null */
    private static ?array $generated = null;

    /**
     * Schema of a request body of the REST API, e.g. input('CustomerInput').
     *
     * @param array<string, array<string, mixed>> $extraProperties added in front (e.g. the id for updates); override generated ones
     * @param list<string> $extraRequired
     * @param list<string> $omit body fields not offered
     * @return array<string, mixed>
     */
    public static function input(string $name, array $extraProperties = [], array $extraRequired = [], array $omit = []): array
    {
        self::$generated ??= OpenApiSchemaGenerator::schemas();
        $openApi = self::$generated[$name] ?? throw new RuntimeException('Unknown input schema "' . $name . '".');

        $properties = $extraProperties;
        foreach ($openApi['properties'] ?? [] as $field => $schema) {
            // Properties given by the tool take precedence (better description/enum).
            if (!in_array($field, $omit, true) && !isset($extraProperties[$field])) {
                $properties[$field] = self::convert($schema);
            }
        }
        $required = array_values(array_unique(array_merge(
            $extraRequired,
            array_values(array_diff($openApi['required'] ?? [], $omit)),
        )));

        return self::object($properties, $required);
    }

    /**
     * Makes fields of a schema optional (the tool fills in a default).
     *
     * @param array<string, mixed> $schema
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    public static function optional(array $schema, array $fields): array
    {
        if (isset($schema['required'])) {
            $schema['required'] = array_values(array_diff($schema['required'], $fields));
            if ($schema['required'] === []) {
                unset($schema['required']);
            }
        }

        return $schema;
    }

    /**
     * @param array<string, array<string, mixed>> $properties
     * @param list<string> $required
     * @return array<string, mixed>
     */
    public static function object(array $properties, array $required = []): array
    {
        $schema = ['type' => 'object', 'properties' => $properties === [] ? new stdClass() : $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /** @return array<string, mixed> */
    public static function id(string $description): array
    {
        return ['type' => 'string', 'description' => $description];
    }

    /** @return array<string, mixed> */
    public static function date(string $description): array
    {
        return ['type' => 'string', 'format' => 'date', 'description' => $description . ' (YYYY-MM-DD)'];
    }

    /** Common paging arguments of the list tools. @return array<string, array<string, mixed>> */
    public static function paging(): array
    {
        return [
            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => McpToolbox::MAX_LIMIT, 'default' => McpToolbox::DEFAULT_LIMIT, 'description' => 'Maximum number of results (default ' . McpToolbox::DEFAULT_LIMIT . ', max ' . McpToolbox::MAX_LIMIT . ').'],
            'page' => ['type' => 'integer', 'minimum' => 1, 'default' => 1, 'description' => 'Page of results, starting at 1. Use it when "has_more" was true.'],
        ];
    }

    /**
     * OpenAPI 3.0 schema → JSON Schema (draft 2020-12 subset understood by MCP clients).
     *
     * @param array<string, mixed> $schema
     * @return array<string, mixed>
     */
    private static function convert(array $schema): array
    {
        if (isset($schema['$ref']) && is_string($schema['$ref'])) {
            $row = substr($schema['$ref'], (int) strrpos($schema['$ref'], '/') + 1);

            return self::rows()[$row] ?? ['type' => 'object'];
        }
        if (($schema['nullable'] ?? false) === true) {
            unset($schema['nullable']);
            if (isset($schema['type']) && is_string($schema['type'])) {
                $schema['type'] = [$schema['type'], 'null'];
            }
        }
        if (isset($schema['items'])) {
            $schema['items'] = $schema['items'] instanceof stdClass || $schema['items'] === [] ? new stdClass() : self::convert($schema['items']);
        }
        if (($schema['format'] ?? null) === 'date-time') {
            $schema['description'] = trim(($schema['description'] ?? '') . ' ISO 8601, e.g. 2026-05-01T18:00.');
        } elseif (($schema['format'] ?? null) === 'date') {
            $schema['description'] = trim(($schema['description'] ?? '') . ' YYYY-MM-DD.');
        }

        return $schema;
    }

    /**
     * Item rows of the request bodies (docs/openapi/components/common.yaml),
     * reduced to the fields that can be written.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function rows(): array
    {
        $string = static fn (string $description = ''): array => $description !== '' ? ['type' => 'string', 'description' => $description] : ['type' => 'string'];
        $number = static fn (string $description): array => ['type' => ['string', 'number'], 'description' => $description];

        return [
            'ContactRow' => self::object([
                'id' => $string('Id of an existing contact (keeps it); omit for a new one.'),
                'salutation' => ['type' => 'string', 'enum' => ['herr', 'frau', 'divers', '']],
                'name' => $string(),
                'job_title' => $string(),
                'phone' => $string(),
                'email' => $string(),
                'street' => $string(),
                'address_line2' => $string(),
                'postal_code' => $string(),
                'city' => $string(),
            ], ['name']) + ['description' => 'Contact person (Ansprechpartner). Sending "contacts" replaces the whole list: contacts not sent are removed.'],
            'DocumentItemRow' => self::object([
                'article_id' => $string('Article id (see search_articles).'),
                'quantity' => $number('Whole number ≥ 1.'),
                'unit_price' => $number('Rental price per day in EUR, decimal point. Optional when creating an offer: the article\'s daily rental price is used.'),
                'line_note' => $string('Note shown below the line.'),
                'sort_order' => $number('Position in the document.'),
            ], ['article_id', 'quantity']) + ['description' => 'Rental item (Mietposition).'],
            'LaborItemRow' => self::object([
                'labor_article_id' => $string('Labor time article id (Arbeitszeit-Artikel).'),
                'labor_user_id' => $string('Employee (optional).'),
                'hours' => $number('Hours or days, see labor_billing_unit.'),
                'labor_billing_unit' => ['type' => 'string', 'enum' => ['hour', 'day']],
            ], ['labor_article_id', 'hours']) + ['description' => 'Labor time item (Arbeitszeit).'],
            'SaleItemRow' => self::object([
                'article_id' => $string(),
                'source' => ['type' => 'string', 'enum' => ['inventory_number', 'stock'], 'description' => 'Specific unit from the rental pool or sales stock.'],
                'inventory_number_id' => $string(),
                'quantity' => $number('Quantity (stock only).'),
                'unit_price' => $number('Price in EUR.'),
                'line_note' => $string(),
            ], ['article_id', 'source']) + ['description' => 'Sale item (Verkauf), offers only.'],
            'FreeTextItemRow' => self::object([
                'description' => $string(),
                'quantity' => $number('Quantity.'),
                'unit_price' => $number('Price in EUR.'),
                'sort_order' => $number('Position in the document.'),
            ], ['description']) + ['description' => 'Free text line (Freitextposition).'],
            'SectionTextRow' => self::object([
                'heading' => $string(),
                'sort_order' => $number('Position in the document.'),
            ], ['heading']) + ['description' => 'Section heading.'],
        ];
    }
}
