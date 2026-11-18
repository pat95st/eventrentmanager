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

        return array_merge($result, $this->tourExtrasOverlapping($articlePlaceholders, $params, $excludeRentalIds, $fromDay, $toDay));
    }

    /**
     * Additional pieces of tour stops (migration 177): 'stops_only' around
     * the stop (from the loading waypoint to the unloading waypoint, if
     * set), 'whole_tour' for the whole tour (largest addition per article).
     *
     * @param string[] $articlePlaceholders
     * @param array<string, string> $params
     * @param string[] $excludeRentalIds
     * @return list<array{rentalId: string, articleId: string, quantity: int, from: DateTimeImmutable, to: DateTimeImmutable}>
     */
    private function tourExtrasOverlapping(array $articlePlaceholders, array $params, array $excludeRentalIds, DateTimeImmutable $fromDay, DateTimeImmutable $toDay): array
    {
        unset($params['from'], $params['to']);
        $exclude = '';
        foreach (array_values($excludeRentalIds) as $i => $rentalId) {
            $exclude .= ' AND s.rental_id <> :x' . $i;
            $params['x' . $i] = $rentalId;
        }
        $stmt = $this->pdo->prepare(
            'SELECT s.rental_id, i.article_id, i.delta, i.allocation,
                    COALESCE(s.setup_at, s.event_start_at) AS stop_from,
                    COALESCE(s.teardown_end_at, s.event_end_at, s.event_start_at) AS stop_to,
                    COALESCE(iw.arrive_at, iw.depart_at) AS issue_at,
                    COALESCE(rw.depart_at, rw.arrive_at) AS return_at_wp,
                    r.start_date, r.end_date, r.pickup_at, r.return_at
             FROM rental_tour_stop_items i
             INNER JOIN rental_tour_stops s ON s.id = i.stop_id
             INNER JOIN rentals r ON r.id = s.rental_id
             LEFT JOIN rental_tour_stops iw ON iw.id = i.issue_waypoint_id
             LEFT JOIN rental_tour_stops rw ON rw.id = i.return_waypoint_id
             WHERE i.delta > 0 AND i.article_id IN (' . implode(', ', $articlePlaceholders) . ')' . $exclude
        );
        $stmt->execute($params);

        $day = static fn (?string $v): ?DateTimeImmutable => $v !== null ? (new DateTimeImmutable($v))->setTime(0, 0) : null;
        $result = [];
        $whole = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['allocation'] === 'whole_tour') {
                $key = $row['rental_id'] . '|' . $row['article_id'];
                if (!isset($whole[$key]) || (int) $row['delta'] > $whole[$key]['quantity']) {
                    $start = $day($row['start_date']);
                    $end = $day($row['end_date']);
                    $pickup = $day($row['pickup_at']);
                    $return = $day($row['return_at']);
                    $whole[$key] = [
                        'rentalId' => (string) $row['rental_id'],
                        'articleId' => (string) $row['article_id'],
                        'quantity' => (int) $row['delta'],
                        'from' => $pickup !== null && $pickup < $start ? $pickup : $start,
                        'to' => $return !== null && $return > $end ? $return : $end,
                    ];
                }
                continue;
            }
            $from = $day($row['stop_from']);
            $to = $day($row['stop_to']);
            $issue = $day($row['issue_at']);
            $back = $day($row['return_at_wp']);
            if ($from === null || $to === null) {
                continue;
            }
            $result[] = [
                'rentalId' => (string) $row['rental_id'],
                'articleId' => (string) $row['article_id'],
                'quantity' => (int) $row['delta'],
                'from' => $issue !== null && $issue < $from ? $issue : $from,
                'to' => $back !== null && $back > $to ? $back : $to,
            ];
        }

        // The same additional pieces travel from stop to stop: per tour and
        // article only the largest number on a day counts, not the sum.
        $byDay = [];
        foreach ($result as $row) {
            $key = $row['rentalId'] . '|' . $row['articleId'];
            for ($d = $row['from'], $guard = 0; $d <= $row['to'] && $guard < 400; $d = $d->modify('+1 day'), $guard++) {
                if ($d < $fromDay || $d > $toDay) {
                    continue;
                }
                $dayKey = $d->format('Y-m-d');
                $byDay[$key][$dayKey] = max($byDay[$key][$dayKey] ?? 0, $row['quantity']);
            }
        }
        $merged = [];
        foreach ($byDay as $key => $days) {
            [$rentalId, $articleId] = explode('|', $key, 2);
            foreach ($days as $dayKey => $quantity) {
                $date = new DateTimeImmutable($dayKey);
                $merged[] = ['rentalId' => $rentalId, 'articleId' => $articleId, 'quantity' => $quantity, 'from' => $date, 'to' => $date];
            }
        }

        return array_values(array_filter(
            array_merge($merged, array_values($whole)),
            static fn (array $r) => $r['from'] <= $toDay && $r['to'] >= $fromDay,
        ));
    }
}
