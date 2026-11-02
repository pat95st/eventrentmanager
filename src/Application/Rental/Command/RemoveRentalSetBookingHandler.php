<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Rental\RentalSetPlanner;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalSetBookingRepositoryInterface;

final class RemoveRentalSetBookingHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalSetBookingRepositoryInterface $bookings,
        private readonly RentalSetPlanner $setPlanner,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof RemoveRentalSetBooking);

        $booking = $this->bookings->findById($command->id);
        if ($booking === null) {
            throw new NotFoundException("Set-Buchung \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->setPlanner->remove($booking);

        return $booking->rentalId;
    }
}
