<?php

declare(strict_types=1);

namespace App\Application\Technician\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\TradeRepositoryInterface;
use DateTimeImmutable;

/**
 * @return list<array{assignment: \App\Domain\Technician\RentalTechnician, rental: \App\Domain\Rental\Rental, tradeNames: string[]}>
 */
final class ListTechnicianRentalsHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RentalTechnicianRepositoryInterface $assignments,
        private readonly RentalRepositoryInterface $rentals,
        private readonly TradeRepositoryInterface $trades,
    ) {
    }

    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListTechnicianRentals);
        $names = [];
        foreach ($this->trades->findAll() as $trade) {
            $names[$trade->id] = $trade->name;
        }
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        $rows = [];
        foreach ($this->assignments->findByUser($query->userId) as $assignment) {
            if (!$assignment->status->grantsAccess()) {
                continue;
            }
            $rental = $this->rentals->findById($assignment->rentalId);
            if ($rental === null || (!$query->includePast && $rental->endDate->format('Y-m-d') < $today)) {
                continue;
            }
            $rows[] = [
                'assignment' => $assignment,
                'rental' => $rental,
                'tradeNames' => array_values(array_filter(array_map(static fn ($id) => $names[$id] ?? null, $assignment->tradeIds))),
            ];
        }
        usort($rows, static fn ($a, $b) => $query->includePast
            ? $b['rental']->startDate <=> $a['rental']->startDate
            : $a['rental']->startDate <=> $b['rental']->startDate);

        return $rows;
    }
}
