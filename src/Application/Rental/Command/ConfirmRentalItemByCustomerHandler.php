<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rental\RentalCustomerChecklistTokenRepositoryInterface;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use DateTimeImmutable;

final class ConfirmRentalItemByCustomerHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalCustomerChecklistTokenRepositoryInterface $tokens,
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ConfirmRentalItemByCustomer);

        $token = $this->tokens->findByToken(trim($command->token));
        if ($token === null) {
            throw new NotFoundException('Diese Checkliste ist nicht (mehr) verfügbar.');
        }

        $item = $this->issuedItems->findById($command->rentalIssuedItemId);
        // Sicherheitsrelevant: der Posten muss WIRKLICH zu der Vermietung
        // gehören, auf die dieser Token ausgestellt wurde — sonst könnte ein
        // Kunde über seinen eigenen, gültigen Link per erratener/fremder ID
        // Posten anderer Vermietungen umschalten.
        if ($item === null || $item->rentalId !== $token->rentalId) {
            throw new NotFoundException('Dieser Posten wurde nicht gefunden.');
        }

        if ($item->returnedAt !== null) {
            // Bereits zurückgegeben: die Kunden-Checkliste zeigt solche
            // Posten ohnehin nicht mehr an (siehe GetRentalCustomerChecklistHandler)
            // — defensiv auch hier, falls die Seite offen war, während
            // zwischenzeitlich die Rückgabe erfasst wurde.
            throw new NotFoundException('Dieser Posten wurde bereits zurückgegeben.');
        }

        $item->confirmedByCustomerAt = $command->confirmed ? new DateTimeImmutable() : null;
        $this->issuedItems->save($item);

        return null;
    }
}
