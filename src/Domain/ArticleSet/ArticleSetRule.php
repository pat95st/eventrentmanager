<?php

declare(strict_types=1);

namespace App\Domain\ArticleSet;

use DateTimeImmutable;

/**
 * Zeitliche Regel für die Vermietung eines Sets. Alle Regeln eines Sets
 * müssen gleichzeitig erfüllt sein (z. B. "nur am Wochenende" UND "jedes
 * Jahr von Mai bis September").
 */
final class ArticleSetRule
{
    public function __construct(
        public readonly string $id,
        public readonly string $setId,
        public readonly ArticleSetRuleType $type,
        public readonly ?string $fromMonthDay = null, // "MM-DD", nur YearlyPeriod
        public readonly ?string $toMonthDay = null,
        public readonly ?DateTimeImmutable $fromDate = null, // nur DateRange
        public readonly ?DateTimeImmutable $toDate = null,
        public int $sortOrder = 0,
    ) {
    }

    /** Erfüllt der Zeitraum (inklusive Start- und Endtag) diese Regel? */
    public function allows(DateTimeImmutable $start, DateTimeImmutable $end): bool
    {
        $start = $start->setTime(0, 0);
        $end = $end->setTime(0, 0);
        if ($end < $start) {
            return false;
        }

        switch ($this->type) {
            case ArticleSetRuleType::Weekends:
                // Start Fr/Sa/So/Mo, Ende spätestens am Montag desselben Wochenendes.
                $offset = ((int) $start->format('N') - 5 + 7) % 7; // Fr=0, Sa=1, So=2, Mo=3
                if ($offset > 3) {
                    return false;
                }

                return $end <= $start->modify('-' . $offset . ' days')->modify('+3 days');

            case ArticleSetRuleType::Weekdays:
                foreach ($this->days($start, $end) as $day) {
                    if ((int) $day->format('N') >= 6) {
                        return false;
                    }
                }

                return true;

            case ArticleSetRuleType::YearlyPeriod:
                if ($this->fromMonthDay === null || $this->toMonthDay === null) {
                    return true;
                }
                foreach ($this->days($start, $end) as $day) {
                    $md = $day->format('m-d');
                    $inside = $this->fromMonthDay <= $this->toMonthDay
                        ? ($md >= $this->fromMonthDay && $md <= $this->toMonthDay)
                        : ($md >= $this->fromMonthDay || $md <= $this->toMonthDay); // über den Jahreswechsel
                    if (!$inside) {
                        return false;
                    }
                }

                return true;

            case ArticleSetRuleType::DateRange:
                return ($this->fromDate === null || $start >= $this->fromDate->setTime(0, 0))
                    && ($this->toDate === null || $end <= $this->toDate->setTime(0, 0));
        }

        return true;
    }

    /** Lesbare Beschreibung, z. B. "jedes Jahr vom 01.05. bis 30.09.". */
    public function describe(): string
    {
        return match ($this->type) {
            ArticleSetRuleType::Weekends => 'nur am Wochenende (Fr–Mo)',
            ArticleSetRuleType::Weekdays => 'nur unter der Woche (Mo–Fr)',
            ArticleSetRuleType::YearlyPeriod => 'jedes Jahr vom ' . self::formatMonthDay($this->fromMonthDay) . ' bis ' . self::formatMonthDay($this->toMonthDay),
            ArticleSetRuleType::DateRange => 'vom ' . ($this->fromDate?->format('d.m.Y') ?? '…') . ' bis ' . ($this->toDate?->format('d.m.Y') ?? '…'),
        };
    }

    public static function formatMonthDay(?string $monthDay): string
    {
        if ($monthDay === null || preg_match('/^(\d{2})-(\d{2})$/', $monthDay, $m) !== 1) {
            return '…';
        }

        return $m[2] . '.' . $m[1] . '.';
    }

    /** @return iterable<DateTimeImmutable> höchstens 400 Tage */
    private function days(DateTimeImmutable $start, DateTimeImmutable $end): iterable
    {
        for ($day = $start, $i = 0; $day <= $end && $i < 400; $day = $day->modify('+1 day'), $i++) {
            yield $day;
        }
    }
}
