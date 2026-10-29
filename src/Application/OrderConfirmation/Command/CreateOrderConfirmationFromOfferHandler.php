<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Numbering\NumberedDocumentType;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\OrderConfirmation\OrderConfirmation;
use App\Domain\OrderConfirmation\OrderConfirmationItem;
use App\Domain\OrderConfirmation\OrderConfirmationRepositoryInterface;
use App\Domain\OrderConfirmation\OrderConfirmationStatus;
use App\Infrastructure\Persistence\Pdo\DocumentNumberSequence;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Übernimmt die Kopfdaten (Kunde, Veranstaltungsort, Zeitraum, Zubehör-
 * pauschale/Kombirabatt) und regulären Mietpositionen eines bestehenden
 * Angebots 1:1 in eine neue Auftragsbestätigung — Arbeitszeit- und
 * Verkaufspositionen werden NICHT übernommen (OrderConfirmation kennt sie
 * nicht, siehe Klassen-Doc). $sourceOfferId dokumentiert die Herkunft, ohne
 * das Angebot selbst zu verändern (anders als
 * CreateInvoiceFromOfferHandler, das Verkaufspositionen als abgerechnet
 * markiert — hier gibt es keine vergleichbare Gegenbuchung).
 */
final class CreateOrderConfirmationFromOfferHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly OfferRepositoryInterface $offers,
        private readonly OrderConfirmationRepositoryInterface $orderConfirmations,
        private readonly DocumentNumberSequence $numberSequence,
        private readonly PDO $pdo,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateOrderConfirmationFromOffer);

        $offer = $this->offers->findById($command->offerId);
        if ($offer === null) {
            throw new NotFoundException("Angebot \"{$command->offerId}\" wurde nicht gefunden.");
        }

        $now = new DateTimeImmutable();

        $this->pdo->beginTransaction();

        try {
            $number = $this->numberSequence->allocateNext(NumberedDocumentType::OrderConfirmation);

            $orderConfirmation = new OrderConfirmation(
                id: Uuid::v4(),
                orderConfirmationNumber: $number,
                customerId: $offer->customerId,
                eventLocation: $offer->eventLocation,
                venueId: $offer->venueId,
                startDate: $offer->startDate,
                endDate: $offer->endDate,
                status: OrderConfirmationStatus::Draft,
                note: $offer->note,
                sourceOfferId: $offer->id,
                createdAt: $now,
                updatedAt: $now,
                accessoryFlatRateEnabled: $offer->accessoryFlatRateEnabled,
                accessoryFlatRatePercent: $offer->accessoryFlatRatePercent,
                discountType: $offer->discountType,
                discountValue: $offer->discountValue,
                // Ansprechpartner und Zahlungsart vom Angebot übernehmen
                // (Migrationen 104/106).
                contactId: $offer->contactId,
                paymentMethodId: $offer->paymentMethodId,
            );

            $this->orderConfirmations->save($orderConfirmation);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        $offerItems = $this->offers->findItemsByOffer($offer->id);
        $items = array_map(
            fn ($item, $index) => new OrderConfirmationItem(
                id: Uuid::v4(),
                orderConfirmationId: $orderConfirmation->id,
                articleId: $item->articleId,
                quantity: $item->quantity,
                unitPricePerDay: $item->unitPricePerDay,
                lineNote: $item->lineNote,
                sortOrder: $index,
            ),
            $offerItems,
            array_keys($offerItems)
        );
        $this->orderConfirmations->replaceItems($orderConfirmation->id, $items);

        return $orderConfirmation->id;
    }
}
