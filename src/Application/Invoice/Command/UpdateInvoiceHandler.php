<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\InvoiceFreeTextItem;
use App\Domain\Invoice\InvoiceItem;
use App\Domain\Invoice\InvoiceLaborItem;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceSectionText;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Settings\PricingSettingsRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\RichText\HtmlSanitizer;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Bearbeitet eine Rechnung. Anders als bei Angeboten (wo nach "Versendet"
 * immerhin noch validUntil änderbar bleibt) gibt es bei einer Rechnung ab
 * "Versendet" fachlich KEIN Feld mehr, das noch sinnvoll änderbar wäre — die
 * Rechnungsnummer ist einmal vergeben und die Rechnung gegenüber dem Kunden
 * vollständig fixiert. Ein Bearbeitungsversuch wirft daher konsequent eine
 * ConflictException, statt (wie bei Offer) einen Teil der Felder still zu
 * ignorieren (siehe Invoice::isEditable(), analog OfferController/View, die
 * das Formular für diesen Fall ohnehin ausblenden).
 */
final class UpdateInvoiceHandler implements CommandHandlerInterface
{
    use ValidatesInvoiceInput;

    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly CustomerRepositoryInterface $customers,
        private readonly VenueRepositoryInterface $venues,
        private readonly ArticleRepositoryInterface $articles,
        private readonly PricingSettingsRepositoryInterface $pricingSettings,
        private readonly LaborTimeArticleRepositoryInterface $laborTimeArticles,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly RentalRepositoryInterface $rentals,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof UpdateInvoice);

        $invoice = $this->invoices->findById($command->id);
        if ($invoice === null) {
            throw new NotFoundException("Rechnung \"{$command->id}\" wurde nicht gefunden.");
        }

        if (!$invoice->isEditable()) {
            throw new ConflictException('Nur eine Rechnung im Status "Entwurf" kann bearbeitet werden.');
        }

        [$errors, $data] = $this->validateInvoiceInput(
            $this->customers,
            $this->venues,
            $this->articles,
            $this->laborTimeArticles,
            $command->customerId,
            $command->startDate,
            $command->endDate,
            $command->eventLocation,
            $command->buyerReference,
            $command->note,
            $command->items,
            $command->laborItems,
            $command->freeTextItems,
            $command->sectionTexts,
            $command->discountType,
            $command->discountValue,
            $this->rentals,
            $command->rentalId,
            $command->flatRateEnabled,
            $command->flatRateAmount,
            $command->logisticsMethod,
            $command->logisticsDate1,
            $command->logisticsDate2,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $invoice->customerId = $data['customerId'];
        $invoice->eventLocation = $data['eventLocation'];
        $invoice->venueId = $data['venueId'];
        $invoice->startDate = $data['startDate'];
        $invoice->endDate = $data['endDate'];
        $invoice->buyerReference = $data['buyerReference'];
        $invoice->note = $data['note'];
        $invoice->discountType = $data['discountType'];
        $invoice->discountValue = $data['discountValue'];
        $invoice->closingText = $this->htmlSanitizer->sanitize($command->closingText);
        $invoice->sourceRentalId = $data['rentalId'];
        $invoice->logisticsMethod = $data['logisticsMethod'];
        $invoice->logisticsDate1 = $data['logisticsDate1'];
        $invoice->logisticsDate2 = $data['logisticsDate2'];

        // Pauschalpreis: siehe CreateInvoiceHandler für die identische
        // Begründung (nur sinnvoll, wenn mindestens eine Mietposition
        // vorhanden ist).
        $hasRentalItems = array_filter($data['items'], static fn (array $item) => $item['isRentalItem']) !== [];
        $invoice->flatRateEnabled = $hasRentalItems && $command->flatRateEnabled;
        $invoice->flatRateAmount = $invoice->flatRateEnabled ? $data['flatRateAmount'] : null;

        // Geschäftsregel: siehe ValidatesInvoiceInput::validateInvoiceInput()
        // für die identische Begründung (dort bereits für den Kombirabatt
        // erzwungen) — hier zusätzlich für die Zubehörpauschale, die kein
        // Teil des Traits ist (accessoryFlatRateEnabled kommt direkt vom
        // Command).
        $onlyLaborItems = $data['items'] === [] && $data['freeTextItems'] === [] && $data['laborItems'] !== [];
        $accessoryFlatRateEnabled = $onlyLaborItems ? false : $command->accessoryFlatRateEnabled;

        // Zubehörpauschale: der gesnapshotete Wert bleibt beim Bearbeiten
        // unverändert erhalten, AUSSER der Nutzer aktiviert die Pauschale
        // gerade jetzt (neu) — dann wird der aktuelle PricingSettings-Wert
        // erneut übernommen (siehe Aufgabenstellung/Invoice-Klassen-Doc).
        if (!$accessoryFlatRateEnabled) {
            $invoice->accessoryFlatRateEnabled = false;
            $invoice->accessoryFlatRatePercent = null;
        } elseif (!$invoice->accessoryFlatRateEnabled) {
            $invoice->accessoryFlatRateEnabled = true;
            $invoice->accessoryFlatRatePercent = $this->pricingSettings->get()->accessoryFlatRatePercent;
        }

        $invoice->updatedAt = new DateTimeImmutable();

        $this->invoices->save($invoice);

        $items = array_map(
            fn (array $item) => new InvoiceItem(
                id: Uuid::v4(),
                invoiceId: $invoice->id,
                articleId: $item['articleId'],
                quantity: $item['quantity'],
                unitPricePerDay: $item['unitPrice'],
                lineNote: $item['lineNote'],
                sortOrder: $item['sortOrder'],
                isRentalItem: $item['isRentalItem'],
            ),
            $data['items']
        );
        $this->invoices->replaceItems($invoice->id, $items);

        $laborItems = array_map(
            fn (array $item) => new InvoiceLaborItem(
                id: Uuid::v4(),
                invoiceId: $invoice->id,
                laborTimeArticleId: $item['laborTimeArticleId'],
                hours: $item['hours'],
                unitRatePerHour: $item['unitRatePerHour'],
            ),
            $data['laborItems']
        );
        $this->invoices->replaceLaborItems($invoice->id, $laborItems);

        $freeTextItems = array_map(
            fn (array $item) => new InvoiceFreeTextItem(
                id: Uuid::v4(),
                invoiceId: $invoice->id,
                description: $item['description'],
                quantity: $item['quantity'],
                unitPrice: $item['unitPrice'],
                sortOrder: $item['sortOrder'],
            ),
            $data['freeTextItems']
        );
        $this->invoices->replaceFreeTextItems($invoice->id, $freeTextItems);

        $sectionTexts = array_map(
            fn (array $sectionText) => new InvoiceSectionText(
                id: Uuid::v4(),
                invoiceId: $invoice->id,
                heading: $sectionText['heading'],
                sortOrder: $sectionText['sortOrder'],
            ),
            $data['sectionTexts']
        );
        $this->invoices->replaceSectionTexts($invoice->id, $sectionTexts);

        return $invoice->id;
    }
}
