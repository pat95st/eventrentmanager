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

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $rental->customerId = $data['customerId'];
        $rental->startDate = $data['startDate'];
        $rental->endDate = $data['endDate'];
        $rental->eventName = $data['eventName'];
        $rental->eventLocation = $data['eventLocation'];
        $rental->venueId = $data['venueId'];
        $rental->deploymentDays = $data['deploymentDays'];
        $rental->updatedAt = new DateTimeImmutable();

        $this->rentals->save($rental);

        return null;
    }
}
