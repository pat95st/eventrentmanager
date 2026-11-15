<?php

declare(strict_types=1);

namespace App\Application\Federation;

use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

/** Small parsing helpers shared by the federation handlers. */
final class FederationInput
{
    public const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/';

    public static function token(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function date(mixed $value, string $field, string $label): DateTimeImmutable
    {
        $text = is_scalar($value) ? trim((string) $value) : '';
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text);
        if ($date === false || $date->format('Y-m-d') !== $text) {
            throw new ValidationException([$field => "Bitte ein gültiges Datum für „{$label}“ angeben."]);
        }

        return $date;
    }

    /** @return array{0: DateTimeImmutable, 1: DateTimeImmutable} */
    public static function period(mixed $start, mixed $end): array
    {
        $from = self::date($start, 'startDate', 'Beginn');
        $to = self::date($end, 'endDate', 'Ende');
        if ($to < $from) {
            throw new ValidationException(['endDate' => 'Das Ende darf nicht vor dem Beginn liegen.']);
        }
        if ($from->diff($to)->days > 366) {
            throw new ValidationException(['endDate' => 'Der Zeitraum darf höchstens ein Jahr umfassen.']);
        }

        return [$from, $to];
    }

    public static function text(mixed $value, int $maxLength): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }
        $text = trim(mb_substr((string) $value, 0, $maxLength));

        return $text === '' ? null : $text;
    }
}
