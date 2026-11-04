<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Application\Statistics\StatisticsReadModelInterface;
use DateTimeImmutable;
use PDO;

/**
 * Aggregat-Abfragen für die Dashboard-Statistiken. Bewusst schlichtes SQL
 * (Gruppierung nach Monat/Fälligkeit in PHP), damit nichts Datenbank-
 * spezifisches nötig ist.
 */
final class MariadbStatisticsReadModel implements StatisticsReadModelInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function rentalsPerMonth(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $this->pdo->prepare('SELECT start_date FROM rentals WHERE start_date BETWEEN :from AND :to');
        $stmt->execute(['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]);
        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
            $month = substr((string) $date, 0, 7);
            $counts[$month] = ($counts[$month] ?? 0) + 1;
        }

        return $counts;
    }

    public function topArticles(DateTimeImmutable $from, DateTimeImmutable $to, int $limit): array
    {
        $limit = max(1, min(100, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT ri.article_id, SUM(ri.quantity) AS qty, COUNT(DISTINCT ri.rental_id) AS rentals
             FROM rental_items ri
             JOIN rentals r ON r.id = ri.rental_id
             WHERE r.start_date BETWEEN :from AND :to AND ri.article_id IS NOT NULL
             GROUP BY ri.article_id
             ORDER BY qty DESC, rentals DESC
             LIMIT $limit"
        );
        $stmt->execute(['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]);

        return array_map(static fn (array $row) => [
            'articleId' => (string) $row['article_id'],
            'quantity' => (int) $row['qty'],
            'rentals' => (int) $row['rentals'],
        ], $stmt->fetchAll());
    }

    public function stockByCategory(): array
    {
        $rows = $this->pdo->query(
            "SELECT a.category_id, c.name, COUNT(*) AS cnt
             FROM inventory_numbers n
             JOIN articles a ON a.id = n.article_id
             LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN sales s ON s.inventory_number_id = n.id
             WHERE s.id IS NULL
             GROUP BY a.category_id, c.name
             ORDER BY cnt DESC, c.name"
        )->fetchAll();

        return array_map(static fn (array $row) => [
            'categoryId' => $row['category_id'] !== null && $row['name'] !== null ? (string) $row['category_id'] : null,
            'name' => $row['name'] !== null ? (string) $row['name'] : 'ohne Kategorie',
            'count' => (int) $row['cnt'],
        ], $rows);
    }

    public function offersByStatus(DateTimeImmutable $from): array
    {
        $stmt = $this->pdo->prepare('SELECT status, COUNT(*) AS cnt FROM offers WHERE created_at >= :from GROUP BY status');
        $stmt->execute(['from' => $from->format('Y-m-d 00:00:00')]);
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(string) $row['status']] = (int) $row['cnt'];
        }

        return $counts;
    }

    public function inspectionDueDates(): array
    {
        $numbers = $this->pdo->query(
            'SELECT n.id, n.number, n.article_id
             FROM inventory_numbers n
             JOIN articles a ON a.id = n.article_id
             LEFT JOIN sales s ON s.inventory_number_id = n.id
             WHERE a.requires_electrical_inspection = 1 AND s.id IS NULL
             ORDER BY n.number'
        )->fetchAll();
        if ($numbers === []) {
            return [];
        }

        // Letzte Prüfung je Exemplar (spätere Zeilen überschreiben frühere).
        $latest = [];
        $inspections = $this->pdo->query(
            'SELECT ei.inventory_number_id, ei.inspected_at, ei.validity_years, ei.passed
             FROM electrical_inspections ei
             JOIN inventory_numbers n ON n.id = ei.inventory_number_id
             JOIN articles a ON a.id = n.article_id
             WHERE a.requires_electrical_inspection = 1
             ORDER BY ei.inspected_at, ei.created_at'
        );
        foreach ($inspections->fetchAll() as $row) {
            $latest[$row['inventory_number_id']] = $row;
        }

        $result = [];
        foreach ($numbers as $number) {
            $inspection = $latest[$number['id']] ?? null;
            $due = null;
            if ($inspection !== null) {
                $due = (new DateTimeImmutable((string) $inspection['inspected_at']))
                    ->modify('+' . max(0, (int) $inspection['validity_years']) . ' years')
                    ->format('Y-m-d');
            }
            $result[] = [
                'inventoryNumberId' => (string) $number['id'],
                'number' => (string) $number['number'],
                'articleId' => (string) $number['article_id'],
                'dueDate' => $due,
                'passed' => $inspection !== null ? (bool) $inspection['passed'] : null,
            ];
        }

        return $result;
    }
}
