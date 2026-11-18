<?php

declare(strict_types=1);

namespace App\Application\Tour\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Application\RentalSeries\MaterialConflictFinder;
use App\Application\Tour\TourSync;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\RentalTechnicianStatus;
use App\Domain\Technician\TechnicianAccess;
use App\Domain\Tour\TourCrewAction;
use App\Domain\Tour\TourCrewResolver;
use App\Domain\Tour\TourRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Warehouse\WarehouseRepositoryInterface;
use DateTimeImmutable;

final class GetTourOverviewHandler implements QueryHandlerInterface
{
    /** @var array<string, string> */
    private array $names = [];

    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly TourRepositoryInterface $tours,
        private readonly TourSync $sync,
        private readonly ?MaterialConflictFinder $conflicts = null,
        private readonly ?RentalTechnicianRepositoryInterface $technicians = null,
        private readonly ?UserRepositoryInterface $users = null,
        private readonly ?ArticleRepositoryInterface $articles = null,
        private readonly ?ManufacturerRepositoryInterface $manufacturers = null,
        private readonly ?WarehouseRepositoryInterface $warehouses = null,
        private readonly ?DateTimeImmutable $now = null,
    ) {
    }

    public function handle(QueryInterface $query): TourOverview
    {
        assert($query instanceof GetTourOverview);
        $rental = $this->rentals->findById($query->rentalId) ?? throw new NotFoundException('Die Veranstaltung wurde nicht gefunden.');
        if (!$rental->isTour) {
            throw new ValidationException(['tour' => 'Diese Veranstaltung ist keine Tour.']);
        }
        $now = $this->now ?? new DateTimeImmutable();
        $plan = $this->sync->plan($rental->id);
        $resolver = $this->sync->resolver($rental->id, $plan);

        // Tour crew (without declined technicians) and their status.
        $statusByUser = [];
        foreach ($this->technicians?->findByRental($rental->id) ?? [] as $assignment) {
            $statusByUser[$assignment->userId] = $assignment->status;
        }
        $deviations = $this->tours->findTechniciansByRental($rental->id);
        $deviationUsers = array_map(static fn ($d) => $d->userId, $deviations);
        $tourCrewIds = [];
        foreach ($statusByUser as $userId => $status) {
            // Users only requested as replacement/addition do not belong to the tour crew itself.
            if ($status !== RentalTechnicianStatus::Declined && !$this->onlyThroughDeviations((string) $userId, $deviations)) {
                $tourCrewIds[] = (string) $userId;
            }
        }
        $crewResolver = new TourCrewResolver($tourCrewIds, $deviations);
        $programs = $this->tours->minuteProgramsByStop($rental->id);

        $entries = [];
        foreach ($plan->ordered() as $stop) {
            $until = $stop->occupiedUntil();
            $locked = $until !== null && $until < $now;
            if (!$stop->isStop()) {
                $entries[] = new TourStopView($stop, null, $locked);
                continue;
            }
            $material = $resolver->effective($stop->id);
            $crew = [];
            foreach ($crewResolver->crewFor($stop->id) as $userId => $deviation) {
                $status = $statusByUser[$userId] ?? null;
                $pending = $status === null || $status === RentalTechnicianStatus::Requested || $status === RentalTechnicianStatus::Declined;
                $role = null;
                if ($deviation !== null) {
                    $role = $deviation->action === TourCrewAction::Replace
                        ? 'Vertretung für ' . $this->userName((string) $deviation->replacesUserId)
                        : 'zusätzlich';
                }
                $crew[] = ['userId' => (string) $userId, 'name' => $this->userName((string) $userId), 'role' => $role, 'pending' => $pending, 'deviation' => $deviation];
            }
            $warnings = [];
            foreach ($crew as $member) {
                if ($member['deviation'] !== null && $member['pending']) {
                    $warnings[] = $member['name'] . ' hat noch nicht zugesagt.';
                }
            }
            $from = $stop->occupiedFrom();
            $conflicts = !$locked && $from !== null && $until !== null
                ? ($this->conflicts?->find($material, $from, $until, [$rental->id]) ?? [])
                : [];
            $entries[] = new TourStopView(
                stop: $stop,
                number: $plan->numberOf($stop->id),
                locked: $locked,
                material: $material,
                deviations: $resolver->deviations($stop->id),
                changes: $resolver->changesAt($stop->id),
                crew: $crew,
                crewDeviations: $crewResolver->deviations($stop->id),
                conflicts: $conflicts,
                minutePrograms: $programs[$stop->id] ?? [],
                warnings: $warnings,
            );
        }
        // Overlapping stops
        $stops = $plan->stops();
        for ($i = 1, $n = count($stops); $i < $n; $i++) {
            $prevEnd = $stops[$i - 1]->occupiedUntil();
            $start = $stops[$i]->occupiedFrom();
            if ($prevEnd !== null && $start !== null && $start < $prevEnd) {
                foreach ($entries as $k => $entry) {
                    if ($entry->stop->id === $stops[$i]->id) {
                        $entries[$k] = new TourStopView($entry->stop, $entry->number, $entry->locked, $entry->material, $entry->deviations, $entry->changes, $entry->crew, $entry->crewDeviations, $entry->conflicts, $entry->minutePrograms, [...$entry->warnings, 'Der Aufbau beginnt vor dem Abbau-Ende des vorherigen Stops.']);
                    }
                }
            }
        }

        $articleIds = $resolver->articleIds();
        $articleNames = [];
        foreach ($articleIds as $articleId) {
            $articleNames[$articleId] = $this->articleName($articleId);
        }
        $articleOptions = [];
        foreach ($this->articles?->search(null, null, [], 2000, 0, true, [], false, true) ?? [] as $article) {
            $articleOptions[$article->id] = $this->articleName($article->id, $article);
        }
        asort($articleOptions);

        $technicians = [];
        foreach ($this->users?->findAllActive() ?? [] as $user) {
            if (TechnicianAccess::isTechnician($user)) {
                $technicians[$user->id] = $user->displayName();
            }
        }
        asort($technicians);
        $warehouses = [];
        foreach ($this->warehouses?->findAll() ?? [] as $warehouse) {
            $warehouses[$warehouse->id] = $warehouse->name;
        }

        return new TourOverview(
            rental: $rental,
            entries: $entries,
            base: $this->sync->baseList($rental->id),
            startLoad: $resolver->startLoad(),
            extraForIssue: $resolver->extraForIssue(),
            articleNames: $articleNames,
            tourCrew: array_combine($tourCrewIds, array_map($this->userName(...), $tourCrewIds)) ?: [],
            technicians: $technicians,
            savedAddresses: $this->tours->findSavedAddresses(),
            warehouses: $warehouses,
            articleOptions: $articleOptions,
        );
    }

    /** @param \App\Domain\Tour\TourStopTechnician[] $deviations */
    private function onlyThroughDeviations(string $userId, array $deviations): bool
    {
        foreach ($deviations as $deviation) {
            if ($deviation->userId === $userId && $deviation->action !== TourCrewAction::Remove) {
                return true;
            }
        }

        return false;
    }

    private function userName(string $userId): string
    {
        return $this->names['u' . $userId] ??= ($this->users?->findById($userId)?->displayName() ?? 'Techniker');
    }

    private function articleName(string $articleId, ?\App\Domain\Article\Article $article = null): string
    {
        if (isset($this->names['a' . $articleId])) {
            return $this->names['a' . $articleId];
        }
        $article ??= $this->articles?->findById($articleId);
        $manufacturer = $article?->manufacturerId !== null ? $this->manufacturers?->findById($article->manufacturerId) : null;

        return $this->names['a' . $articleId] = $article !== null ? ArticleDisplayName::of($article, $manufacturer) : 'Artikel';
    }
}
