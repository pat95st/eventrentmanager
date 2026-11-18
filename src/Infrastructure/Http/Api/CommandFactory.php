<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api;

use App\Domain\Exception\ValidationException;
use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use UnitEnum;

/**
 * Builds the existing application commands (the same ones the web UI
 * dispatches) from an API request body, so the API reuses every validation
 * and business rule of the handlers.
 *
 * Each constructor parameter is resolved, in this order, from
 *  1. $overrides (e.g. the id from the path),
 *  2. the request body under the snake_case name of the parameter,
 *  3. $current — the current state for PATCH (camelCase keys, usually the
 *     entity's public properties, see props()), so omitted fields keep their
 *     value,
 *  4. the parameter's default value, or null if nullable.
 * Values are coerced to the declared parameter type (string, int, float,
 * bool, array, DateTimeImmutable, backed enums). Missing required values and
 * type errors are reported together as ValidationException (HTTP 422).
 */
final class CommandFactory
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $body request body (snake_case keys)
     * @param array<string, mixed> $overrides camelCase parameter name => value (already typed)
     * @param array<string, mixed>|null $current camelCase name => current value (PATCH)
     * @param array<string, string> $aliases body field => parameter name, for renamed fields
     * @return T
     */
    public static function create(string $class, array $body, array $overrides = [], ?array $current = null, array $aliases = []): object
    {
        foreach ($aliases as $field => $param) {
            if (array_key_exists($field, $body)) {
                $body[Normalizer::snake($param)] = $body[$field];
                unset($body[$field]);
            }
        }

        $constructor = (new ReflectionClass($class))->getConstructor();
        $args = [];
        $errors = [];
        foreach ($constructor?->getParameters() ?? [] as $param) {
            $name = $param->getName();
            $field = Normalizer::snake($name);
            try {
                if (array_key_exists($name, $overrides)) {
                    $args[$name] = $overrides[$name];
                } elseif (array_key_exists($field, $body)) {
                    $args[$name] = self::coerce($body[$field], $param, $field);
                } elseif ($current !== null && array_key_exists($name, $current)) {
                    $args[$name] = self::fromCurrent($current[$name], $param, $name);
                } elseif ($param->isDefaultValueAvailable()) {
                    $args[$name] = $param->getDefaultValue();
                } elseif ($param->allowsNull()) {
                    $args[$name] = null;
                } else {
                    $errors[$field] = 'Pflichtfeld fehlt.';
                }
            } catch (InvalidField $e) {
                $errors[$field] = $e->getMessage();
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        /** @var T */
        return new $class(...$args);
    }

    /**
     * Public properties of an entity/DTO as camelCase map, for $current.
     *
     * @return array<string, mixed>
     */
    public static function props(object $object): array
    {
        return get_object_vars($object);
    }

    private static function coerce(mixed $value, ReflectionParameter $param, string $field): mixed
    {
        $type = $param->getType();
        if ($value === null) {
            if ($param->allowsNull()) {
                return null;
            }
            throw new InvalidField('Darf nicht null sein.');
        }
        if (!$type instanceof ReflectionNamedType) {
            throw new InvalidField('Wird über die API nicht unterstützt.');
        }

        $name = $type->getName();

        return match (true) {
            $name === 'string' => is_scalar($value) ? (is_bool($value) ? ($value ? '1' : '0') : (string) $value) : throw new InvalidField('Text erwartet.'),
            $name === 'int' => is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) ? (int) $value : throw new InvalidField('Ganze Zahl erwartet.'),
            $name === 'float' => is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)) ? (float) $value : throw new InvalidField('Zahl erwartet.'),
            $name === 'bool' => self::bool($value),
            $name === 'array' => is_array($value) ? $value : throw new InvalidField('Liste/Objekt erwartet.'),
            $name === 'mixed' => $value,
            is_a($name, DateTimeInterface::class, true) => self::date($value),
            is_subclass_of($name, BackedEnum::class) => self::enum($name, $value),
            default => throw new InvalidField('Wird über die API nicht unterstützt.'),
        };
    }

    private static function fromCurrent(mixed $value, ReflectionParameter $param, string $name): mixed
    {
        $type = $param->getType();
        if ($value === null && !$param->allowsNull() && $type instanceof ReflectionNamedType) {
            return match (true) {
                $param->isDefaultValueAvailable() => $param->getDefaultValue(),
                $type->getName() === 'bool' => false,
                $type->getName() === 'string' => '',
                $type->getName() === 'array' => [],
                default => throw new InvalidField('Pflichtfeld fehlt.'),
            };
        }
        if ($value === null || !$type instanceof ReflectionNamedType) {
            return $value;
        }
        $target = $type->getName();
        if ($target === 'string') {
            return match (true) {
                $value instanceof BackedEnum => (string) $value->value,
                $value instanceof UnitEnum => $value->name,
                $value instanceof DateTimeInterface => $value->format(Normalizer::isDateField($name) ? 'Y-m-d' : 'Y-m-d\TH:i'),
                is_bool($value) => $value ? '1' : '0',
                is_float($value) => rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.'),
                is_scalar($value) => (string) $value,
                default => $value,
            };
        }
        if ($target === 'int' && is_numeric($value)) {
            return (int) $value;
        }
        if ($target === 'float' && is_numeric($value)) {
            return (float) $value;
        }
        if (is_subclass_of($target, BackedEnum::class) && (is_string($value) || is_int($value))) {
            return $target::from($value);
        }

        return $value;
    }

    private static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (in_array($value, [1, '1', 'true', 'on', 'yes'], true)) {
            return true;
        }
        if (in_array($value, [0, '0', 'false', 'off', 'no', ''], true)) {
            return false;
        }
        throw new InvalidField('true oder false erwartet.');
    }

    private static function date(mixed $value): DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidField('Datum im Format ISO 8601 erwartet.');
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            throw new InvalidField('Datum im Format ISO 8601 erwartet.');
        }
    }

    /** @param class-string<BackedEnum> $enum */
    private static function enum(string $enum, mixed $value): BackedEnum
    {
        $case = is_string($value) || is_int($value) ? $enum::tryFrom($value) : null;
        if ($case === null) {
            throw new InvalidField('Erlaubte Werte: ' . implode(', ', array_map(static fn (BackedEnum $c) => (string) $c->value, $enum::cases())) . '.');
        }

        return $case;
    }
}
