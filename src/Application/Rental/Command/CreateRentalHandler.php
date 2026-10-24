<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateRentalHandler implements CommandHandlerInterface
{
    use ValidatesRentalInput;

    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerRepositoryInterface $customers,
        private readonly VenueRepositoryInterface $venues,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateRental);

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

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $rental = new Rental(
            id: Uuid::v4(),
            customerId: $data['customerId'],
            startDate: $data['startDate'],
            endDate: $data['endDate'],
            eventName: $data['eventName'],
            eventLocation: $data['eventLocation'],
            venueId: $data['venueId'],
            deploymentDays: $data['deploymentDays'],
            createdAt: $now,
            updatedAt: $now,
        );

        $this->rentals->save($rental);

        return $rental->id;
    }
}
