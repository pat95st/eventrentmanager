<?php

declare(strict_types=1);

namespace App\Application\ElectricalInspection\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\ElectricalInspection\ElectricalInspectionRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class UpdateElectricalInspectionHandler implements CommandHandlerInterface
{
    private const ALLOWED_VALIDITY_YEARS = [1, 2];

    public function __construct(private readonly ElectricalInspectionRepositoryInterface $inspections)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateElectricalInspection);

        $inspection = $this->inspections->findById($command->id);
        if ($inspection === null) {
            throw new NotFoundException("Elektroprüfung \"{$command->id}\" wurde nicht gefunden.");
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

        $inspection->inspectedAt = $inspectedAt;
        $inspection->validityYears = $validityYears;
        $inspection->passed = $command->passed;
        $inspection->inspector = $this->nullIfBlank($command->inspector);
        $inspection->notes = $this->nullIfBlank($command->notes);
        $inspection->updatedAt = new DateTimeImmutable();

        $this->inspections->save($inspection);

        return null;
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
