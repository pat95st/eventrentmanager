<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\TravelCost\TravelCostDocumentType;
use App\Domain\TravelCost\TravelCostLine;
use App\Domain\TravelCost\TravelCostMode;
use App\Domain\TravelCost\TravelCostSection;
use App\Domain\TravelCost\TravelCostSectionRepositoryInterface;
use DateTimeImmutable;
use PDO;

/** document_travel_costs + document_travel_cost_lines (migration 182). */
final class MariadbTravelCostSectionRepository implements TravelCostSectionRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findFor(TravelCostDocumentType $type, string $documentId): ?TravelCostSection
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_travel_costs WHERE ' . $type->column() . ' = :id');
        $stmt->execute(['id' => $documentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $lines = $this->pdo->prepare('SELECT * FROM document_travel_cost_lines WHERE travel_cost_id = :id ORDER BY sort_order, id');
        $lines->execute(['id' => $row['id']]);

        return new TravelCostSection(
            id: $row['id'],
            documentType: $type,
            documentId: $documentId,
            mode: TravelCostMode::tryFrom((string) $row['mode']) ?? TravelCostMode::Summary,
            vehiclesLabel: $row['vehicles_label'],
            routeLabel: $row['route_label'],
            ratePerKm: (string) $row['rate_per_km'],
            trips: TravelCostSection::tripsFromJson((string) $row['trips']),
            lines: array_map(static fn (array $l) => new TravelCostLine(
                id: $l['id'],
                description: (string) $l['description'],
                kilometers: (string) $l['kilometers'],
                ratePerKm: (string) $l['rate_per_km'],
                detail: $l['detail'],
                tripDate: $l['trip_date'] !== null ? new DateTimeImmutable($l['trip_date']) : null,
                sortOrder: (int) $l['sort_order'],
            ), $lines->fetchAll(PDO::FETCH_ASSOC)),
            sourceRentalId: $row['source_rental_id'],
        );
    }

    public function save(TravelCostSection $section): void
    {
        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            // One section per document: a new section replaces an existing one.
            $this->pdo->prepare('DELETE FROM document_travel_costs WHERE ' . $section->documentType->column() . ' = :doc AND id <> :id')
                ->execute(['doc' => $section->documentId, 'id' => $section->id]);

            $column = $section->documentType->column();
            $this->pdo->prepare(
                'INSERT INTO document_travel_costs (id, ' . $column . ', source_rental_id, mode, vehicles_label, route_label, rate_per_km, trips)
                 VALUES (:id, :doc, :rental, :mode, :vehicles, :route, :rate, :trips)
                 ON DUPLICATE KEY UPDATE source_rental_id = VALUES(source_rental_id), mode = VALUES(mode),
                    vehicles_label = VALUES(vehicles_label), route_label = VALUES(route_label),
                    rate_per_km = VALUES(rate_per_km), trips = VALUES(trips)'
            )->execute([
                'id' => $section->id,
                'doc' => $section->documentId,
                'rental' => $section->sourceRentalId,
                'mode' => $section->mode->value,
                'vehicles' => $section->vehiclesLabel,
                'route' => $section->routeLabel,
                'rate' => $section->ratePerKm,
                'trips' => $section->tripsJson(),
            ]);

            $this->pdo->prepare('DELETE FROM document_travel_cost_lines WHERE travel_cost_id = :id')->execute(['id' => $section->id]);
            $insert = $this->pdo->prepare(
                'INSERT INTO document_travel_cost_lines (id, travel_cost_id, sort_order, trip_date, description, detail, kilometers, rate_per_km)
                 VALUES (:id, :section, :sort, :date, :description, :detail, :km, :rate)'
            );
            foreach (array_values($section->lines) as $i => $line) {
                $insert->execute([
                    'id' => $line->id,
                    'section' => $section->id,
                    'sort' => $i,
                    'date' => $line->tripDate?->format('Y-m-d'),
                    'description' => $line->description,
                    'detail' => $line->detail,
                    'km' => $line->kilometers,
                    'rate' => $line->ratePerKm,
                ]);
            }
            if ($startedTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function deleteFor(TravelCostDocumentType $type, string $documentId): void
    {
        $this->pdo->prepare('DELETE FROM document_travel_costs WHERE ' . $type->column() . ' = :id')->execute(['id' => $documentId]);
    }
}
