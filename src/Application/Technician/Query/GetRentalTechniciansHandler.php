<?php

declare(strict_types=1);

namespace App\Application\Technician\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\TechnicianAccess;
use App\Domain\Technician\TechnicianProfileRepositoryInterface;
use App\Domain\Technician\TradeRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;

final class GetRentalTechniciansHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RentalTechnicianRepositoryInterface $assignments,
        private readonly TradeRepositoryInterface $trades,
        private readonly UserRepositoryInterface $users,
        private readonly TechnicianProfileRepositoryInterface $profiles,
    ) {
    }

    public function handle(QueryInterface $query): RentalTechnicianTeam
    {
        assert($query instanceof GetRentalTechnicians);
        $allTrades = $this->trades->findAll();
        $names = [];
        foreach ($allTrades as $trade) {
            $names[$trade->id] = $trade->name;
        }
        $rows = [];
        $assignedUserIds = [];
        foreach ($this->assignments->findByRental($query->rentalId) as $assignment) {
            $assignedUserIds[] = $assignment->userId;
            $rows[] = [
                'assignment' => $assignment,
                'user' => $this->users->findById($assignment->userId),
                'tradeNames' => array_values(array_filter(array_map(static fn ($id) => $names[$id] ?? null, $assignment->tradeIds))),
                'requiresConfirmation' => $this->profiles->find($assignment->userId)?->requiresConfirmation ?? true,
            ];
        }
        $available = array_values(array_filter(
            $this->users->findAllActive(),
            static fn ($u) => TechnicianAccess::isTechnician($u) && !in_array($u->id, $assignedUserIds, true),
        ));
        usort($available, static fn ($a, $b) => strcasecmp($a->displayName(), $b->displayName()));

        return new RentalTechnicianTeam($rows, $allTrades, $this->trades->tradeIdsForRental($query->rentalId), $available);
    }
}
