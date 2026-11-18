<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use App\Domain\User\User;
use BackedEnum;
use DateTimeInterface;
use UnitEnum;

/**
 * Converts domain entities and query DTOs into JSON-ready arrays by reading
 * their public properties: camelCase becomes snake_case, enums become their
 * value, dates become ISO 8601 ("Y-m-d" for date-only fields, see
 * isDateField()). Properties whose name hints at secrets are never exposed.
 */
final class Normalizer
{
    private const MAX_DEPTH = 8;

    /** Property names (camelCase) that are never part of an API response. */
    private const SECRET_PROPERTIES = [
        'password', 'passwordHash', 'tokenHash', 'secret', 'clientSecret', 'apiKey', 'apiSecret',
        'accessToken', 'refreshToken', 'outboundToken', 'inboundTokenHash', 'codeHash', 'totpSecret',
        'privateKey', 'webhookSecret',
    ];

    /**
     * @param list<string> $exclude additional camelCase property names to drop (top level only)
     */
    public static function normalize(mixed $value, array $exclude = [], int $depth = 0): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return null;
        }
        if ($value === null || is_scalar($value)) {
            return is_float($value) && (is_nan($value) || is_infinite($value)) ? null : $value;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }
        if ($value instanceof User) {
            // Users only appear as references (no e-mail, permissions, 2FA data).
            return ['id' => $value->id, 'name' => $value->displayName()];
        }
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if ($value instanceof UnitEnum) {
            return $value->name;
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[is_string($key) ? self::snake($key) : $key] = self::normalize($item, [], $depth + 1);
            }

            return $result;
        }
        if (is_object($value)) {
            $result = [];
            foreach (get_object_vars($value) as $name => $item) {
                if (in_array($name, self::SECRET_PROPERTIES, true) || in_array($name, $exclude, true)) {
                    continue;
                }
                if ($item instanceof DateTimeInterface && self::isDateField($name)) {
                    $result[self::snake($name)] = $item->format('Y-m-d');
                    continue;
                }
                $result[self::snake($name)] = self::normalize($item, [], $depth + 1);
            }

            return $result;
        }

        return null;
    }

    /**
     * @param iterable<mixed> $items
     * @param list<string> $exclude
     * @return list<mixed>
     */
    public static function list(iterable $items, array $exclude = []): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[] = self::normalize($item, $exclude);
        }

        return $result;
    }

    /** "dailyRentalPrice" → "daily_rental_price" (already snake_case keys stay unchanged). */
    public static function snake(string $name): string
    {
        return strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])/', '_$1', $name));
    }

    /** "daily_rental_price" → "dailyRentalPrice". */
    public static function camel(string $name): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
    }

    /** Date-only properties: startDate, endDate, dueDate, acquisitionDate, date, …On, birthday. */
    public static function isDateField(string $name): bool
    {
        return preg_match('/(^date$|Date$|^date[A-Z]|On$|^birthday$)/', $name) === 1;
    }
}
