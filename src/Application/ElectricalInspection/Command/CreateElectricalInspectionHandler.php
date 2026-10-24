<?php

declare(strict_types=1);

namespace App\Application\ElectricalInspection\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\ElectricalInspection\ElectricalInspection;
use App\Domain\ElectricalInspection\ElectricalInspectionRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateElectricalInspectionHandler implements CommandHandlerInterface
{
    private const ALLOWED_VALIDITY_YEARS = [1, 2];

    public function __construct(
        private readonly ElectricalInspectionRepositoryInterface $inspections,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateElectricalInspection);

        if ($this->inventoryNumbers->findById($command->inventoryNumberId) === null) {
            throw new NotFoundException("Inventurnummer \"{$command->inventoryNumberId}\" wurde nicht gefunden.");
        }

        $errors = [];

        $inspectedAt = $this->parseDate($command->inspectedAt);
        if ($inspectedAt === null) {
            $errors['inspectedAt'] = 'Bitte ein Prüfdatum angeben.';
        }

        $validityYears = filter_var($command->validityYears, FILTER_VALIDATE_INT);
        if ($validityYears === false || !in_array($validityYears, self::ALLOWED_VALIDITY_YEARS, true)) {
            $errors['validityYears'] = 'Bitte eine gültige Gültigkeitsdauer wählen (1 oder 2 Jahre).';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $inspection = new ElectricalInspection(
            id: Uuid::v4(),
            inventoryNumberId: $command->inventoryNumberId,
            inspectedAt: $inspectedAt,
            validityYears: $validityYears,
            passed: $command->passed,
            inspector: $this->nullIfBlank($command->inspector),
            notes: $this->nullIfBlank($command->notes),
            createdAt: $now,
            updatedAt: $now,
        );

        $this->inspections->save($inspection);

        return $inspection->id;
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        return $value !== null && $value !== '' ? new DateTimeImmutable($value) : null;
    }

    private function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
