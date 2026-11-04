<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Warehouse\WarehouseRepositoryInterface;
use DateTimeImmutable;

final class UpdateRentalLogisticsHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly WarehouseRepositoryInterface $warehouses,
        private readonly ?\App\Application\Rental\RentalScheduleRules $schedule = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateRentalLogistics);

        $rental = $this->rentals->findById($command->rentalId);
        if ($rental === null) {
            throw new NotFoundException("Veranstaltung \"{$command->rentalId}\" wurde nicht gefunden.");
        }

        $errors = [];

        $pickupWarehouseId = $this->nullIfEmpty($command->pickupWarehouseId);
        if ($pickupWarehouseId !== null && $this->warehouses->findById($pickupWarehouseId) === null) {
            $errors['pickupWarehouseId'] = 'Der ausgewählte Lagerort für die Ausgabe wurde nicht gefunden.';
        }

        $returnWarehouseId = $this->nullIfEmpty($command->returnWarehouseId);
        if ($returnWarehouseId !== null && $this->warehouses->findById($returnWarehouseId) === null) {
            $errors['returnWarehouseId'] = 'Der ausgewählte Lagerort für die Rücklieferung wurde nicht gefunden.';
        }

        $pickupAt = $this->parseDateTime($command->pickupAt, 'pickupAt', $errors);
        $returnAt = $this->parseDateTime($command->returnAt, 'returnAt', $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $rental->pickupWarehouseId = $pickupWarehouseId;
        $rental->pickupAt = $pickupAt;
        $rental->returnWarehouseId = $returnWarehouseId;
        $rental->returnAt = $returnAt;
        $rental->transportNote = $this->nullIfEmpty($command->transportNote);
        $rental->updatedAt = new DateTimeImmutable();

        // Betriebsurlaub / exklusive Veranstaltungen (Migration 130)
        if ($this->schedule !== null) {
            $holidays = $this->schedule->holidaysDuring($rental->startDate, $rental->endDate);
            $errors = $this->schedule->holidayLogisticsErrors($holidays, $pickupAt, $returnAt);
            foreach (['pickupAt' => [$pickupAt, 'Ausgabe'], 'returnAt' => [$returnAt, 'Rücklieferung']] as $field => [$moment, $label]) {
                if ($moment !== null && !isset($errors[$field]) && ($inHoliday = $this->schedule->holidaysDuring($moment, $moment)) !== []) {
                    $errors[$field] = "Die {$label} fällt in den Betriebsurlaub ({$inHoliday[0]->label()}).";
                }
            }
            $blocking = $this->schedule->blockingError($rental);
            if ($blocking !== null) {
                $errors['pickupAt'] = ($errors['pickupAt'] ?? '') === '' ? $blocking : $errors['pickupAt'] . ' ' . $blocking;
            }
            if ($errors !== []) {
                throw new ValidationException($errors);
            }
        }

        $this->rentals->save($rental);

        return null;
    }

    /** @param array<string, string> $errors */
    private function parseDateTime(?string $value, string $field, array &$errors): ?DateTimeImmutable
    {
        $value = $this->nullIfEmpty($value);
        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            $errors[$field] = 'Bitte einen gültigen Termin angeben.';

            return null;
        }
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
