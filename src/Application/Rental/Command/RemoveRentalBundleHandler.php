<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Rental\RentalBundlePlanner;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalBundleRepositoryInterface;

/** Entfernt ein gebuchtes Bundle samt Bestandteil-Mengen aus einer Vermietung (Migration 107). */
final class RemoveRentalBundleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalBundleRepositoryInterface $rentalBundles,
        private readonly RentalBundlePlanner $bundlePlanner,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof RemoveRentalBundle);

        $rentalBundle = $this->rentalBundles->findById($command->id);
        if ($rentalBundle === null) {
            throw new NotFoundException("Bundle-Buchung \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->bundlePlanner->remove($rentalBundle);

        return $rentalBundle->rentalId;
    }
}
