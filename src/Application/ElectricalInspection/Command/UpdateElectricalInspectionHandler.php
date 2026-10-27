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

        $protectiveConductorResistanceOhm = self::normalizeMeasurement($command->protectiveConductorResistanceOhm, 3, $errors, 'protectiveConductorResistanceOhm');
        $insulationResistanceMohm = self::normalizeMeasurement($command->insulationResistanceMohm, 2, $errors, 'insulationResistanceMohm');
        $protectiveConductorCurrentMa = self::normalizeMeasurement($command->protectiveConductorCurrentMa, 2, $errors, 'protectiveConductorCurrentMa');
        $touchCurrentMa = self::normalizeMeasurement($command->touchCurrentMa, 2, $errors, 'touchCurrentMa');

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $inspection->inspectedAt = $inspectedAt;
        $inspection->validityYears = $validityYears;
        $inspection->passed = $command->passed;
        $inspection->inspector = $this->nullIfBlank($command->inspector);
        $inspection->protectiveConductorResistanceOhm = $protectiveConductorResistanceOhm;
        $inspection->insulationResistanceMohm = $insulationResistanceMohm;
        $inspection->protectiveConductorCurrentMa = $protectiveConductorCurrentMa;
        $inspection->touchCurrentMa = $touchCurrentMa;
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

    /**
     * Normalisiert einen optionalen Messwert (dt. Komma erlaubt, nicht-negativ)
     * auf die angegebene Anzahl Nachkommastellen; leer bleibt leer. Bei einem
     * ungültigen (nicht-numerischen oder negativen) Wert wird $errors[$field]
     * gesetzt und null zurückgegeben.
     *
     * @param array<string, string> $errors
     */
    private static function normalizeMeasurement(?string $raw, int $decimals, array &$errors, string $field): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim($raw));
        $value = filter_var($normalized, FILTER_VALIDATE_FLOAT);

        if ($value === false || $value < 0) {
            $errors[$field] = 'Bitte einen gültigen, nicht-negativen Messwert angeben.';

            return null;
        }

        return number_format($value, $decimals, '.', '');
    }
}
