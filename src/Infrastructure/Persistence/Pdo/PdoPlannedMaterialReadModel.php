<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Application\RentalSeries\PlannedMaterialReadModelInterface;
use DateTimeImmutable;
use PDO;

final class PdoPlannedMaterialReadModel implements PlannedMaterialReadModelInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function plannedByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT article_id, SUM(quantity) AS quantity FROM rental_items WHERE rental_id = :id GROUP BY article_id');
        $stmt->execute(['id' => $rentalId]);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[(string) $row['article_id']] = (int) $row['quantity'];
        }

        return $result;
    }

    public function plannedOverlapping(array $articleIds, DateTimeImmutable $from, DateTimeImmutable $to, array $excludeRentalIds): array
    {
        if ($articleIds === []) {
            return [];
        }
        $params = [
            // Pickup/return can lie outside start/end: search wide, filter exactly below.
            'from' => $from->modify('-60 days')->format('Y-m-d'),
            'to' => $to->modify('+60 days')->format('Y-m-d'),
        ];
        $articlePlaceholders = [];
        foreach (array_values($articleIds) as $i => $articleId) {
            $articlePlaceholders[] = ':a' . $i;
            $params['a' . $i] = $articleId;
        }
        $sql = 'SELECT ri.rental_id, ri.article_id, ri.quantity, r.start_date, r.end_date, r.pickup_at, r.return_at
                FROM rental_items ri
                INNER JOIN rentals r ON r.id = ri.rental_id
                WHERE r.start_date <= :to AND r.end_date >= :from
                  AND ri.article_id IN (' . implode(', ', $articlePlaceholders) . ')';
        foreach (array_values($excludeRentalIds) as $i => $rentalId) {
            $sql .= ' AND ri.rental_id <> :x' . $i;
            $params['x' . $i] = $rentalId;
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $fromDay = $from->setTime(0, 0);
        $toDay = $to->setTime(0, 0);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $start = new DateTimeImmutable($row['start_date']);
            $end = new DateTimeImmutable($row['end_date']);
            if ($row['pickup_at'] !== null && ($pickup = (new DateTimeImmutable($row['pickup_at']))->setTime(0, 0)) < $start) {
                $start = $pickup;
            }
            if ($row['return_at'] !== null && ($return = (new DateTimeImmutable($row['return_at']))->setTime(0, 0)) > $end) {
                $end = $return;
            }
            if ($start > $toDay || $end < $fromDay) {
                continue;
            }
            $result[] = [
                'rentalId' => (string) $row['rental_id'],
                'articleId' => (string) $row['article_id'],
                'quantity' => (int) $row['quantity'],
                'from' => $start,
                'to' => $end,
            ];
        }

        return $result;
    }
}
