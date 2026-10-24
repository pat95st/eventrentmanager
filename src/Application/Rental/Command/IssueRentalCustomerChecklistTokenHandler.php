<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Rental\RentalCustomerChecklistTokenIssuer;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalRepositoryInterface;

/** Manuelles "Link erzeugen"/"Link erneuern" auf der Veranstaltungs-Detailseite. */
final class IssueRentalCustomerChecklistTokenHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly RentalCustomerChecklistTokenIssuer $issuer,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof IssueRentalCustomerChecklistToken);

        if ($this->rentals->findById($command->rentalId) === null) {
            throw new NotFoundException("Vermietung \"{$command->rentalId}\" wurde nicht gefunden.");
        }

        return $this->issuer->issue($command->rentalId, $command->forceNew);
    }
}
