<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\CompanyHoliday\CompanyHoliday;
use App\Domain\CompanyHoliday\CompanyHolidayRepositoryInterface;
use DateTimeImmutable;
use PDO;

/** Betriebsurlaub (Migration 130). */
final class MariadbCompanyHolidayRepository implements CompanyHolidayRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $rows = $this->pdo->query('SELECT * FROM company_holidays ORDER BY start_date, end_date')->fetchAll();

        return array_map($this->hydrate(...), $rows);
    }

    public function findOverlapping(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM company_holidays WHERE start_date <= :to AND end_date >= :from ORDER BY start_date');
        $stmt->execute(['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findById(string $id): ?CompanyHoliday
    {
        $stmt = $this->pdo->prepare('SELECT * FROM company_holidays WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(CompanyHoliday $holiday): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO company_holidays (id, start_date, end_date, note, created_at, updated_at)
             VALUES (:id, :start_date, :end_date, :note, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE start_date = VALUES(start_date), end_date = VALUES(end_date),
                note = VALUES(note), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'id' => $holiday->id,
            'start_date' => $holiday->startDate->format('Y-m-d'),
            'end_date' => $holiday->endDate->format('Y-m-d'),
            'note' => $holiday->note,
            'created_at' => $holiday->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $holiday->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM company_holidays WHERE id = :id')->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): CompanyHoliday
    {
        return new CompanyHoliday(
            id: $row['id'],
            startDate: new DateTimeImmutable($row['start_date']),
            endDate: new DateTimeImmutable($row['end_date']),
            note: $row['note'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
