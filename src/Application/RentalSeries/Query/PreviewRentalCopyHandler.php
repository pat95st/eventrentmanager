<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Application\RentalSeries\PlannedMaterialReadModelInterface;
use App\Application\RentalSeries\RentalCopier;
use App\Application\RentalSeries\SeriesSupport;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Rental\Rental;
use App\Domain\RentalSeries\CopyScope;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\RentalTechnicianStatus;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Warehouse\WarehouseRepositoryInterface;

final class PreviewRentalCopyHandler implements QueryHandlerInterface
{
    /** @var array<string, string> */
    private array $articleNames = [];

    public function __construct(
        private readonly SeriesSupport $support,
        private readonly RentalCopier $copier,
        private readonly PlannedMaterialReadModelInterface $planned,
        private readonly ?ArticleRepositoryInterface $articles = null,
        private readonly ?ManufacturerRepositoryInterface $manufacturers = null,
        private readonly ?RentalTechnicianRepositoryInterface $technicians = null,
        private readonly ?UserRepositoryInterface $users = null,
        private readonly ?WarehouseRepositoryInterface $warehouses = null,
    ) {
    }

    public function handle(QueryInterface $query): RentalCopyPreview
    {
        assert($query instanceof PreviewRentalCopy);
        $scopes = CopyScope::fromValues($query->scopes);
        if ($scopes === []) {
            throw new ValidationException(['scopes' => 'Bitte mindestens einen Bereich zum Übernehmen auswählen.']);
        }
        $source = $this->support->rental($query->sourceRentalId);
        $targets = [];
        foreach (array_unique($query->targetRentalIds) as $targetId) {
            if ($targetId === $source->id) {
                continue;
            }
            $target = $this->support->rental($targetId);
            if ($source->seriesId === null || $target->seriesId !== $source->seriesId) {
                throw new ValidationException(['targets' => 'Daten können nur zwischen Terminen derselben Serie übernommen werden.']);
            }
            $started = $this->copier->isStarted($target);
            $targets[] = ['rental' => $target, 'started' => $started, 'changes' => $started ? [] : $this->changes($source, $target, $scopes)];
        }
        if ($targets === []) {
            throw new ValidationException(['targets' => 'Bitte mindestens einen Termin auswählen.']);
        }

        return new RentalCopyPreview($source, $scopes, $targets);
    }

    /**
     * @param CopyScope[] $scopes
     * @return list<array{area: string, before: string, after: string}>
     */
    private function changes(Rental $source, Rental $target, array $scopes): array
    {
        $changes = [];
        $add = static function (string $area, string $before, string $after) use (&$changes): void {
            if ($before !== $after) {
                $changes[] = ['area' => $area, 'before' => $before, 'after' => $after];
            }
        };
        if (in_array(CopyScope::Location, $scopes, true)) {
            $add('Ort', $target->eventLocation ?? '–', $source->eventLocation ?? '–');
            $add('Transportnotiz', $target->transportNote ?? '–', $source->transportNote ?? '–');
        }
        if (in_array(CopyScope::Logistics, $scopes, true)) {
            $offsetPickup = $source->pickupAt !== null ? RentalCopier::dayOffset($source->startDate, $source->pickupAt) : null;
            $offsetReturn = $source->returnAt !== null ? RentalCopier::dayOffset($source->endDate, $source->returnAt) : null;
            $newPickup = $offsetPickup !== null && $source->pickupAt !== null
                ? $target->startDate->setTime(0, 0)->modify(($offsetPickup >= 0 ? '+' : '') . $offsetPickup . ' days')->setTime((int) $source->pickupAt->format('H'), (int) $source->pickupAt->format('i'))
                : null;
            $newReturn = $offsetReturn !== null && $source->returnAt !== null
                ? $target->endDate->setTime(0, 0)->modify(($offsetReturn >= 0 ? '+' : '') . $offsetReturn . ' days')->setTime((int) $source->returnAt->format('H'), (int) $source->returnAt->format('i'))
                : null;
            $fmt = static fn ($d) => $d?->format('d.m.Y H:i') ?? '–';
            $add('Ausgabe', $this->warehouseName($target->pickupWarehouseId) . ' ' . $fmt($target->pickupAt), $this->warehouseName($source->pickupWarehouseId) . ' ' . $fmt($newPickup));
            $add('Rückgabe', $this->warehouseName($target->returnWarehouseId) . ' ' . $fmt($target->returnAt), $this->warehouseName($source->returnWarehouseId) . ' ' . $fmt($newReturn));
        }
        if (in_array(CopyScope::Material, $scopes, true)) {
            $before = $this->planned->plannedByRental($target->id);
            $after = $this->planned->plannedByRental($source->id);
            foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $articleId) {
                $add('Material: ' . $this->articleName((string) $articleId), (string) ($before[$articleId] ?? 0) . '×', (string) ($after[$articleId] ?? 0) . '×');
            }
        }
        if (in_array(CopyScope::Technicians, $scopes, true) && $this->technicians !== null) {
            $names = function (string $rentalId): string {
                $list = [];
                foreach ($this->technicians?->findByRental($rentalId) ?? [] as $assignment) {
                    if ($assignment->status !== RentalTechnicianStatus::Declined) {
                        $list[] = $this->users?->findById($assignment->userId)?->displayName() ?? 'Techniker';
                    }
                }
                sort($list);

                return $list === [] ? '–' : implode(', ', $list);
            };
            $add('Techniker', $names($target->id), $names($source->id));
        }
        if (in_array(CopyScope::MinuteProgram, $scopes, true)) {
            $changes[] = ['area' => 'Minutenprogramm', 'before' => 'bisherige Programme', 'after' => 'Programme der Quelle (Uhrzeiten auf das Datum verschoben)'];
        }
        if (in_array(CopyScope::PowerPlan, $scopes, true)) {
            $changes[] = ['area' => 'Stromplanung', 'before' => 'bisherige Planung', 'after' => 'Planung der Quelle'];
        }

        return $changes;
    }

    private function articleName(string $articleId): string
    {
        if (!isset($this->articleNames[$articleId])) {
            $article = $this->articles?->findById($articleId);
            $manufacturer = $article?->manufacturerId !== null ? $this->manufacturers?->findById($article->manufacturerId) : null;
            $this->articleNames[$articleId] = $article !== null ? ArticleDisplayName::of($article, $manufacturer) : 'Artikel';
        }

        return $this->articleNames[$articleId];
    }

    private function warehouseName(?string $id): string
    {
        if ($id === null) {
            return '';
        }

        return $this->warehouses?->findById($id)?->name ?? '';
    }
}
