<?php

declare(strict_types=1);

namespace App\Application\Offer\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\Offer\OfferStatus;
use DateTimeImmutable;

/** Versendet -> Abgelehnt. Muss zuerst als versendet markiert worden sein (kein Sprung aus dem Entwurf). */
final class MarkOfferDeclinedHandler implements CommandHandlerInterface
{
    public function __construct(private readonly OfferRepositoryInterface $offers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MarkOfferDeclined);

        $offer = $this->offers->findById($command->id);
        if ($offer === null) {
            throw new NotFoundException("Angebot \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($offer->status !== OfferStatus::Sent) {
            throw new ConflictException('Nur ein versendetes Angebot kann als abgelehnt markiert werden.');
        }

        $offer->status = OfferStatus::Declined;
        $offer->updatedAt = new DateTimeImmutable();
        $this->offers->save($offer);

        return null;
    }
}
