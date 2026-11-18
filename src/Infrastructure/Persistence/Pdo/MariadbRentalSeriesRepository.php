<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\RentalSeries\RecurrenceRule;
use App\Domain\RentalSeries\RecurrenceType;
use App\Domain\RentalSeries\RentalSeries;
use App\Domain\RentalSeries\RentalSeriesRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbRentalSeriesRepository implements RentalSeriesRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(RentalSeries $series): void
    {
        $rule = $series->rule;
        $stmt = $this->pdo->prepare(
            'INSERT INTO rental_series (
                id, name_template, recurrence_type, interval_n, weekdays, month_day, week_of_month, weekday,
                manual_dates, ends_on, occurrence_count, anchor_date, source_rental_id, ended_at, created_at, updated_at
             ) VALUES (
                :id, :name_template, :recurrence_type, :interval_n, :weekdays, :month_day, :week_of_month, :weekday,
                :manual_dates, :ends_on, :occurrence_count, :anchor_date, :source_rental_id, :ended_at, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                name_template = VALUES(name_template), recurrence_type = VALUES(recurrence_type),
                interval_n = VALUES(interval_n), weekdays = VALUES(weekdays), month_day = VALUES(month_day),
                week_of_month = VALUES(week_of_month), weekday = VALUES(weekday), manual_dates = VALUES(manual_dates),
                ends_on = VALUES(ends_on), occurrence_count = VALUES(occurrence_count), anchor_date = VALUES(anchor_date),
                source_rental_id = VALUES(source_rental_id), ended_at = VALUES(ended_at), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'id' => $series->id,
            'name_template' => $series->nameTemplate,
            'recurrence_type' => $rule->type->value,
            'interval_n' => $rule->interval,
            'weekdays' => $rule->weekdays !== [] ? implode(',', $rule->weekdays) : null,
            'month_day' => $rule->monthDay,
            'week_of_month' => $rule->weekOfMonth,
            'weekday' => $rule->weekday,
            'manual_dates' => $rule->manualDates !== [] ? implode(',', $rule->manualDates) : null,
            'ends_on' => $rule->endsOn?->format('Y-m-d'),
            'occurrence_count' => $rule->occurrenceCount,
            'anchor_date' => $series->anchorDate->format('Y-m-d'),
            'source_rental_id' => $series->sourceRentalId,
            'ended_at' => $series->endedAt?->format('Y-m-d H:i:s'),
            'created_at' => $series->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $series->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?RentalSeries
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_series WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        return array_map($this->hydrate(...), $this->pdo->query('SELECT * FROM rental_series ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC));
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM rental_series WHERE id = :id')->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): RentalSeries
    {
        $list = static fn (?string $value): array => $value === null || $value === '' ? [] : explode(',', $value);

        $rule = new RecurrenceRule(
            type: RecurrenceType::tryFrom((string) $row['recurrence_type']) ?? RecurrenceType::Manual,
            interval: (int) $row['interval_n'],
            weekdays: array_map('intval', $list($row['weekdays'])),
            monthDay: $row['month_day'] !== null ? (int) $row['month_day'] : null,
            weekOfMonth: $row['week_of_month'] !== null ? (int) $row['week_of_month'] : null,
            weekday: $row['weekday'] !== null ? (int) $row['weekday'] : null,
            manualDates: $list($row['manual_dates']),
            endsOn: $row['ends_on'] !== null ? new DateTimeImmutable($row['ends_on']) : null,
            occurrenceCount: $row['occurrence_count'] !== null ? (int) $row['occurrence_count'] : null,
        );

        return new RentalSeries(
            id: (string) $row['id'],
            rule: $rule,
            anchorDate: new DateTimeImmutable($row['anchor_date']),
            nameTemplate: $row['name_template'],
            sourceRentalId: $row['source_rental_id'],
            endedAt: $row['ended_at'] !== null ? new DateTimeImmutable($row['ended_at']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
