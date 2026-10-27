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
use App\Domain\Numbering\NumberedDocumentType;
use App\Domain\Offer\Offer;
use App\Domain\Offer\OfferFreeTextItem;
use App\Domain\Offer\OfferItem;
use App\Domain\Offer\OfferLaborItem;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\Offer\OfferSaleItem;
use App\Domain\Offer\OfferSectionText;
use App\Domain\Offer\OfferStatus;
use App\Domain\Sale\SaleRepositoryInterface;
use App\Domain\Settings\PricingSettingsRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\Persistence\Pdo\DocumentNumberSequence;
use App\Infrastructure\RichText\HtmlSanitizer;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;
use Throwable;

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
        private readonly UserRepositoryInterface $users,
        private readonly DocumentNumberSequence $numberSequence,
        private readonly PDO $pdo,
        private readonly HtmlSanitizer $htmlSanitizer,
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
            $this->users,
            $command->customerId,
            $command->startDate,
            $command->endDate,
            $command->eventLocation,
            $command->validUntil,
            $command->note,
            $command->items,
            $command->laborItems,
            $command->saleItems,
            $command->freeTextItems,
            $command->sectionTexts,
            $command->discountType,
            $command->discountValue,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        // Geschäftsregel: siehe ValidatesInvoiceInput::validateInvoiceInput()
        // für die identische Begründung (dort bereits für den Kombirabatt
        // erzwungen) — die Zubehörpauschale ist kein Teil des Traits
        // (accessoryFlatRateEnabled kommt direkt vom Command), daher hier
        // zusätzlich anhand derselben Bedingung erzwungen.
        $onlyLaborItems = $data['items'] === [] && $data['freeTextItems'] === [] && $data['laborItems'] !== [];
        $accessoryFlatRateEnabled = $onlyLaborItems ? false : $command->accessoryFlatRateEnabled;

        // Zubehörpauschale wird beim Anlegen — sofern aktiv — vom aktuellen
        // Stand der PricingSettings auf das Angebot SELBST gesnapshotet
        // (Snapshot-Prinzip, siehe Offer-/Invoice-Klassen-Doc).
        $accessoryFlatRatePercent = $accessoryFlatRateEnabled
            ? $this->pricingSettings->get()->accessoryFlatRatePercent
            : null;

        $now = new DateTimeImmutable();

        // Nummernvergabe UND das Speichern der Angebots-Kopfzeile laufen in
        // derselben Transaktion (siehe DocumentNumberSequence-Klassen-Doc):
        // schlägt das Speichern fehl, wird auch die Nummernvergabe
        // zurückgerollt. Die anschließenden replace*Items()-Aufrufe haben
        // bewusst KEINE eigene äußere Transaktion hier (PDO unterstützt
        // keine echten verschachtelten Transaktionen) — sie öffnen wie
        // gewohnt ihre eigene, in sich atomare Transaktion.
        $this->pdo->beginTransaction();

        try {
            $offerNumber = $this->numberSequence->allocateNext(NumberedDocumentType::Offer);

            $offer = new Offer(
                id: Uuid::v4(),
                offerNumber: $offerNumber,
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
                accessoryFlatRateEnabled: $accessoryFlatRateEnabled,
                accessoryFlatRatePercent: $accessoryFlatRatePercent,
                discountType: $data['discountType'],
                discountValue: $data['discountValue'],
                closingText: $this->htmlSanitizer->sanitize($command->closingText),
            );

            $this->offers->save($offer);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        $items = array_map(
            fn (array $item) => new OfferItem(
                id: Uuid::v4(),
                offerId: $offer->id,
                articleId: $item['articleId'],
                quantity: $item['quantity'],
                unitPricePerDay: $item['unitPrice'],
                lineNote: $item['lineNote'],
                sortOrder: $item['sortOrder'],
            ),
            $data['items']
        );
        $this->offers->replaceItems($offer->id, $items);

        $laborItems = array_map(
            fn (array $item) => new OfferLaborItem(
                id: Uuid::v4(),
                offerId: $offer->id,
                laborTimeArticleId: $item['laborTimeArticleId'],
                userId: $item['userId'],
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

        $freeTextItems = array_map(
            fn (array $item) => new OfferFreeTextItem(
                id: Uuid::v4(),
                offerId: $offer->id,
                description: $item['description'],
                quantity: $item['quantity'],
                unitPrice: $item['unitPrice'],
                sortOrder: $item['sortOrder'],
            ),
            $data['freeTextItems']
        );
        $this->offers->replaceFreeTextItems($offer->id, $freeTextItems);

        $sectionTexts = array_map(
            fn (array $sectionText) => new OfferSectionText(
                id: Uuid::v4(),
                offerId: $offer->id,
                heading: $sectionText['heading'],
                sortOrder: $sectionText['sortOrder'],
            ),
            $data['sectionTexts']
        );
        $this->offers->replaceSectionTexts($offer->id, $sectionTexts);

        return $offer->id;
    }
}
