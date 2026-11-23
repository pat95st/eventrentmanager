<?php

declare(strict_types=1);

namespace App\Application\Vehicle\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Vehicle\Vehicle;
use App\Domain\Vehicle\VehicleRepositoryInterface;
use DateTimeImmutable;

/**
 * Saves the logbook settings of a vehicle (migration 183).
 *
 * Rules:
 * - The options for external drivers are kept as entered even while the
 *   login-free capture is off, so switching it on again restores them;
 *   they only take effect together with logbookAnonymousAllowed.
 * - The odometer tolerance is a whole number of km (empty = 0).
 * - With the odometer check switched on, the initial odometer reading is
 *   required: it is the reading the first trip has to start with.
 */
final class SaveVehicleLogbookSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly VehicleRepositoryInterface $vehicles)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SaveVehicleLogbookSettings);

        $vehicle = $this->vehicles->findById($command->vehicleId)
            ?? throw new NotFoundException('Das Fahrzeug wurde nicht gefunden.');

        $errors = [];
        $tolerance = $this->parseKm($command->odometerToleranceKm, 'odometerToleranceKm', 'Bitte die Toleranz als ganze Zahl (km) angeben.', $errors) ?? 0;
        $initial = $this->parseKm($command->odometerInitialKm, 'odometerInitialKm', 'Bitte den Kilometerstand als ganze Zahl (km) angeben.', $errors);
        if ($command->odometerCheck && $initial === null && !isset($errors['odometerInitialKm'])) {
            $errors['odometerInitialKm'] = 'Für die Kilometerstand-Prüfung bitte den aktuellen Kilometerstand als Anfangswert angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $vehicle->logbookQrEnabled = $command->qrEnabled;
        // Migration 185: the QR code gets its token when QR capture is switched on the first time.
        if ($command->qrEnabled && $vehicle->logbookQrToken === null) {
            $vehicle->logbookQrToken = \App\Application\Logbook\Command\RegenerateVehicleQrTokenHandler::newToken();
        }
        $vehicle->logbookAnonymousAllowed = $command->anonymousAllowed;
        $vehicle->logbookAnonymousPrivateAllowed = $command->anonymousPrivateAllowed;
        $vehicle->logbookAnonymousRequiresApproval = $command->anonymousRequiresApproval;
        $vehicle->logbookOdometerCheck = $command->odometerCheck;
        $vehicle->logbookOdometerToleranceKm = $tolerance;
        $vehicle->logbookOdometerInitialKm = $initial;
        $vehicle->updatedAt = new DateTimeImmutable();

        $this->vehicles->save($vehicle);

        return null;
    }

    /** @param array<string, string> $errors */
    private function parseKm(?string $value, string $field, string $message, array &$errors): ?int
    {
        $value = $value !== null ? trim(str_replace(['.', ' '], '', $value)) : '';
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $value) !== 1 || (int) $value > Vehicle::MAX_ODOMETER_KM) {
            $errors[$field] = $message;

            return null;
        }

        return (int) $value;
    }
}
