<?php

declare(strict_types=1);

namespace App\Application\Offer\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use App\Domain\Offer\Offer;
use App\Domain\Offer\OfferItem;
use App\Domain\Offer\OfferLaborItem;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\Offer\OfferSaleItem;
use App\Domain\Offer\OfferStatus;
use App\Domain\Sale\SaleRepositoryInterface;
use App\Domain\Settings\PricingSettingsRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateOfferHandler implements CommandHandlerInterface
{
    use ValidatesOfferInput;

    public function __construct(
        private readonly OfferRepositoryInterface $offers,
        private readonly CustomerRepositoryInterface $customers,
        private readonly VenueRepositoryInterface $venues,
        private readonly ArticleRepositoryInterface $articles,
        private readonly PricingSettingsRepositoryInterface $pricingSettings,
        private readonly LaborTimeArticleRepositoryInterface $laborTimeArticles,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly SaleRepositoryInterface $sales,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateOffer);

        [$errors, $data] = $this->validateOfferInput(
            $this->customers,
            $this->venues,
            $this->articles,
            $this->laborTimeArticles,
            $this->inventoryNumbers,
            $this->sales,
            $command->customerId,
            $command->startDate,
            $command->endDate,
            $command->eventLocation,
            $command->validUntil,
            $command->note,
            $command->items,
            $command->laborItems,
            $command->saleItems,
            $command->discountType,
            $command->discountValue,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        // Zubehörpauschale wird beim Anlegen — sofern aktiv — vom aktuellen
        // Stand der PricingSettings auf das Angebot SELBST gesnapshotet
        // (Snapshot-Prinzip, siehe Offer-/Invoice-Klassen-Doc).
        $accessoryFlatRatePercent = $command->accessoryFlatRateEnabled
            ? $this->pricingSettings->get()->accessoryFlatRatePercent
            : null;

        $now = new DateTimeImmutable();
        $offer = new Offer(
            id: Uuid::v4(),
            customerId: $data['customerId'],
            eventLocation: $data['eventLocation'],
            venueId: $data['venueId'],
            startDate: $data['startDate'],
            endDate: $data['endDate'],
            validUntil: $data['validUntil'],
            status: OfferStatus::Draft,
            note: $data['note'],
            convertedRentalId: null,
            createdAt: $now,
            updatedAt: $now,
            accessoryFlatRateEnabled: $command->accessoryFlatRateEnabled,
            accessoryFlatRatePercent: $accessoryFlatRatePercent,
            discountType: $data['discountType'],
            discountValue: $data['discountValue'],
        );

        $this->offers->save($offer);

        $items = array_map(
            fn (array $item) => new OfferItem(
                id: Uuid::v4(),
                offerId: $offer->id,
                articleId: $item['articleId'],
                quantity: $item['quantity'],
                unitPricePerDay: $item['unitPrice'],
                lineNote: $item['lineNote'],
            ),
            $data['items']
        );
        $this->offers->replaceItems($offer->id, $items);

        $laborItems = array_map(
            fn (array $item) => new OfferLaborItem(
                id: Uuid::v4(),
                offerId: $offer->id,
                laborTimeArticleId: $item['laborTimeArticleId'],
                hours: $item['hours'],
                unitRatePerHour: $item['unitRatePerHour'],
            ),
            $data['laborItems']
        );
        $this->offers->replaceLaborItems($offer->id, $laborItems);

        $saleItems = array_map(
            fn (array $item) => new OfferSaleItem(
                id: Uuid::v4(),
                offerId: $offer->id,
                articleId: $item['articleId'],
                inventoryNumberId: $item['inventoryNumberId'],
                quantity: $item['quantity'],
                unitPrice: $item['unitPrice'],
                lineNote: $item['lineNote'],
            ),
            $data['saleItems']
        );
        $this->offers->replaceSaleItems($offer->id, $saleItems);

        return $offer->id;
    }
}
