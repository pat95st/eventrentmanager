<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

/**
 * Parses the delay and time inputs of the FOH run sheet and the run sheet
 * API (migration 158).
 */
final class MinuteProgramLiveClock
{
    /** Delays beyond ±24 h are certainly typos. */
    public const MAX_DELAY_SECONDS = 86400;

    public static function clampDelay(int $seconds): int
    {
        return max(-self::MAX_DELAY_SECONDS, min(self::MAX_DELAY_SECONDS, $seconds));
    }

    /**
     * Accepts "5" / "+5" / "-2" (minutes), "5:30" / "-1:15" (minutes:seconds)
     * and "1:05:00" (hours:minutes:seconds).
     *
     * @throws ValidationException
     */
    public static function parseDelay(string $input): int
    {
        $value = str_replace([' ', '−', ','], ['', '-', '.'], trim($input));
        if ($value === '') {
            return 0;
        }
        if (preg_match('/^([+-]?)(\d+(?:\.\d+)?)$/', $value, $m) === 1) {
            $seconds = (int) round((float) $m[2] * 60);
        } elseif (preg_match('/^([+-]?)(\d+):([0-5]?\d)(?::([0-5]?\d))?$/', $value, $m) === 1) {
            $seconds = isset($m[4]) && $m[4] !== ''
                ? (int) $m[2] * 3600 + (int) $m[3] * 60 + (int) $m[4]
                : (int) $m[2] * 60 + (int) $m[3];
        } else {
            throw new ValidationException(['delay' => 'Bitte den Verzug als Minuten (z. B. 5 oder -2) oder als mm:ss (z. B. 4:30) angeben.']);
        }
        if (abs($seconds) > self::MAX_DELAY_SECONDS) {
            throw new ValidationException(['delay' => 'Der Verzug darf höchstens 24 Stunden betragen.']);
        }

        return $m[1] === '-' ? -$seconds : $seconds;
    }

    /**
     * Parses an actual start time: an ISO 8601 timestamp, or a wall-clock
     * time "HH:MM" / "HH:MM:SS" which is placed on the day closest to
     * $reference (so 00:10 after a planned 23:50 lands on the next day).
     *
     * @throws ValidationException
     */
    public static function parseTime(string $input, DateTimeImmutable $reference): DateTimeImmutable
    {
        $value = trim($input);
        if (preg_match('/^(\d{1,2})[:.](\d{2})(?:[:.](\d{2}))?$/', $value, $m) === 1) {
            if ((int) $m[1] > 23 || (int) $m[2] > 59 || (int) ($m[3] ?? 0) > 59) {
                throw new ValidationException(['time' => 'Ungültige Uhrzeit.']);
            }
            $time = $reference->setTime((int) $m[1], (int) $m[2], (int) ($m[3] ?? 0));
            $diff = $time->getTimestamp() - $reference->getTimestamp();
            if ($diff > 43200) {
                $time = $time->modify('-1 day');
            } elseif ($diff < -43200) {
                $time = $time->modify('+1 day');
            }

            return $time;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $value) === 1) {
            try {
                return (new DateTimeImmutable($value))->setTimezone($reference->getTimezone());
            } catch (\Exception) {
                // fall through
            }
        }

        throw new ValidationException(['time' => 'Bitte die Startzeit als HH:MM oder HH:MM:SS angeben.']);
    }
}
