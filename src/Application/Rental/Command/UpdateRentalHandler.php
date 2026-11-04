<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use DateTimeImmutable;

final class UpdateRentalHandler implements CommandHandlerInterface
{
    use ValidatesRentalInput;

    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerRepositoryInterface $customers,
        private readonly VenueRepositoryInterface $venues,
        private readonly ?\App\Application\Rental\RentalScheduleRules $schedule = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateRental);

        $rental = $this->rentals->findById($command->id);
        if ($rental === null) {
            throw new NotFoundException("Vermietung \"{$command->id}\" wurde nicht gefunden.");
        }

        [$errors, $data] = $this->validateRentalInput(
            $this->customers,
            $this->venues,
            $command->customerId,
            $command->startDate,
            $command->endDate,
            $command->eventName,
            $command->eventLocation,
            $command->deploymentDays,
        );

        $pickupAt = $this->parseMoment($command->pickupAt, 'pickupAt', $errors);
        $returnAt = $this->parseMoment($command->returnAt, 'returnAt', $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        // Betriebsurlaub: bestätigen muss man nur Urlaube, in die die
        // Veranstaltung durch diese Änderung neu hineinfällt.
        $previousHolidayIds = array_map(static fn ($h) => $h->id, $this->schedule?->holidaysDuring($rental->startDate, $rental->endDate) ?? []);

        $rental->customerId = $data['customerId'];
        $rental->startDate = $data['startDate'];
        $rental->endDate = $data['endDate'];
        $rental->eventName = $data['eventName'];
        $rental->eventLocation = $data['eventLocation'];
        $rental->venueId = $data['venueId'];
        $rental->deploymentDays = $data['deploymentDays'];
        $rental->blocksOtherRentals = $command->blocksOtherRentals;
        if ($pickupAt !== null) {
            $rental->pickupAt = $pickupAt;
        }
        if ($returnAt !== null) {
            $rental->returnAt = $returnAt;
        }
        $rental->updatedAt = new DateTimeImmutable();

        $holidays = $this->schedule?->holidaysDuring($rental->startDate, $rental->endDate) ?? [];
        $newHolidays = array_filter($holidays, static fn ($h) => !in_array($h->id, $previousHolidayIds, true));
        $this->applyScheduleRules($this->schedule, $rental, $holidays, $newHolidays !== [], $command->holidayConfirmed, $errors);
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->rentals->save($rental);

        return null;
    }
}
