<?php

declare(strict_types=1);

namespace App\Application\Offer\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use App\Domain\Offer\OfferFreeTextItem;
use App\Domain\Offer\OfferItem;
use App\Domain\Offer\OfferLaborItem;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\Offer\OfferSaleItem;
use App\Domain\Offer\OfferSectionText;
use App\Domain\Offer\OfferStatus;
use App\Domain\Sale\SaleRepositoryInterface;
use App\Domain\Settings\PricingSettingsRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\RichText\HtmlSanitizer;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Bearbeitet ein Angebot. Solange der Status "Entwurf" ist, sind alle Felder
 * (inkl. Positionen) frei änderbar. Ab "Versendet" gilt das Angebot als dem
 * Kunden gegenüber fixiert: nur noch das "gültig bis"-Datum darf sich noch
 * ändern (siehe Aufgabenstellung), alle übrigen übermittelten Werte werden
 * dann bewusst ignoriert (die gespeicherten Werte bleiben erhalten) statt
 * einen Fehler zu werfen — die Oberfläche (offers/form.php) blendet das
 * Formular für diesen Fall ohnehin weitgehend aus/deaktiviert es.
 */
final class UpdateOfferHandler implements CommandHandlerInterface
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
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof UpdateOffer);

        $offer = $this->offers->findById($command->id);
        if ($offer === null) {
            throw new NotFoundException("Angebot \"{$command->id}\" wurde nicht gefunden.");
        }

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
            $command->freeTextItems,
            $command->sectionTexts,
            $command->discountType,
            $command->discountValue,
        );

        // Das Startdatum-/Enddatum-Feld muss auch außerhalb des Entwurfs
        // gültig geparst werden können, weil validUntil unabhängig davon
        // immer übernommen wird — ein Fehler in den (dann ignorierten)
        // übrigen Feldern soll das Ändern von validUntil nicht blockieren.
        if ($offer->status !== OfferStatus::Draft) {
            $validUntilErrors = array_intersect_key($errors, ['validUntil' => true]);
            if ($validUntilErrors !== []) {
                throw new ValidationException($validUntilErrors);
            }

            $offer->validUntil = $data['validUntil'];
            $offer->updatedAt = new DateTimeImmutable();
            $this->offers->save($offer);

            return $offer->id;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        // Sperre analog zum bestehenden "einmal verkauft/einmal umgewandelt"-
        // Muster: enthält das Angebot bereits abgerechnete Verkaufspositionen
        // (siehe OfferSaleItem::$fulfilledSaleId, gesetzt durch
        // CreateInvoiceFromOfferHandler), wird die GESAMTE Bearbeitung
        // verweigert — ein Ersetzen der Positionsliste würde diese
        // Verknüpfung sonst verwaisen lassen.
        if ($this->hasFulfilledSaleItems($offer->id)) {
            throw new ConflictException('Dieses Angebot enthält bereits abgerechnete Verkaufspositionen und kann daher nicht mehr bearbeitet werden.');
        }

        $offer->customerId = $data['customerId'];
        $offer->eventLocation = $data['eventLocation'];
        $offer->venueId = $data['venueId'];
        $offer->startDate = $data['startDate'];
        $offer->endDate = $data['endDate'];
        $offer->validUntil = $data['validUntil'];
        $offer->note = $data['note'];
        $offer->discountType = $data['discountType'];
        $offer->discountValue = $data['discountValue'];
        $offer->closingText = $this->htmlSanitizer->sanitize($command->closingText);

        // Geschäftsregel: siehe ValidatesInvoiceInput::validateInvoiceInput()
        // für die identische Begründung (dort bereits für den Kombirabatt
        // erzwungen) — hier zusätzlich für die Zubehörpauschale, die kein
        // Teil des Traits ist (accessoryFlatRateEnabled kommt direkt vom
        // Command).
        $onlyLaborItems = $data['items'] === [] && $data['freeTextItems'] === [] && $data['laborItems'] !== [];
        $accessoryFlatRateEnabled = $onlyLaborItems ? false : $command->accessoryFlatRateEnabled;

        // Zubehörpauschale: siehe UpdateInvoiceHandler für die identische
        // Begründung (Snapshot bleibt erhalten, außer bei Reaktivierung).
        if (!$accessoryFlatRateEnabled) {
            $offer->accessoryFlatRateEnabled = false;
            $offer->accessoryFlatRatePercent = null;
        } elseif (!$offer->accessoryFlatRateEnabled) {
            $offer->accessoryFlatRateEnabled = true;
            $offer->accessoryFlatRatePercent = $this->pricingSettings->get()->accessoryFlatRatePercent;
        }

        $offer->updatedAt = new DateTimeImmutable();

        $this->offers->save($offer);

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

    private function hasFulfilledSaleItems(string $offerId): bool
    {
        foreach ($this->offers->findSaleItemsByOffer($offerId) as $saleItem) {
            if ($saleItem->isFulfilled()) {
                return true;
            }
        }

        return false;
    }
}
