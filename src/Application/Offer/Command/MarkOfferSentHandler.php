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

/** Entwurf -> Versendet. Nur aus dem Entwurf-Status heraus möglich. */
final class MarkOfferSentHandler implements CommandHandlerInterface
{
    public function __construct(private readonly OfferRepositoryInterface $offers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MarkOfferSent);

        $offer = $this->offers->findById($command->id);
        if ($offer === null) {
            throw new NotFoundException("Angebot \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($offer->status !== OfferStatus::Draft) {
            throw new ConflictException('Nur ein Angebot im Status "Entwurf" kann als versendet markiert werden.');
        }

        $offer->status = OfferStatus::Sent;
        $offer->updatedAt = new DateTimeImmutable();
        $this->offers->save($offer);

        return null;
    }
}
