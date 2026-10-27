<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceFreeTextItem;
use App\Domain\Invoice\InvoiceItem;
use App\Domain\Invoice\InvoiceLaborItem;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceSectionText;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Settings\PricingSettingsRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
use App\Infrastructure\RichText\HtmlSanitizer;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Legt eine eigenständige Rechnung als Entwurf an (analog CreateOfferHandler).
 * invoiceNumber/invoiceDate/dueDate/isSmallBusiness/vatRatePercent bleiben
 * bewusst NULL — sie werden erst beim Versenden vergeben/gesnapshotet
 * (siehe MarkInvoiceSentHandler).
 */
final class CreateInvoiceHandler implements CommandHandlerInterface
{
    use ValidatesInvoiceInput;

    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly CustomerRepositoryInterface $customers,
        private readonly VenueRepositoryInterface $venues,
        private readonly ArticleRepositoryInterface $articles,
        private readonly PricingSettingsRepositoryInterface $pricingSettings,
        private readonly LaborTimeArticleRepositoryInterface $laborTimeArticles,
        private readonly UserRepositoryInterface $users,
        private readonly HtmlSanitizer $htmlSanitizer,
        private readonly RentalRepositoryInterface $rentals,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateInvoice);

        [$errors, $data] = $this->validateInvoiceInput(
            $this->customers,
            $this->venues,
            $this->articles,
            $this->laborTimeArticles,
            $this->users,
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

        // Geschäftsregel: siehe ValidatesInvoiceInput::validateInvoiceInput()
        // für die identische Begründung (dort bereits für den Kombirabatt
        // erzwungen) — die Zubehörpauschale ist kein Teil des Traits
        // (accessoryFlatRateEnabled kommt direkt vom Command), daher hier
        // zusätzlich anhand derselben Bedingung erzwungen.
        $onlyLaborItems = $data['items'] === [] && $data['freeTextItems'] === [] && $data['laborItems'] !== [];
        $accessoryFlatRateEnabled = $onlyLaborItems ? false : $command->accessoryFlatRateEnabled;

        // Pauschalpreis ist nur sinnvoll, wenn mindestens eine Mietposition
        // (isRentalItem = true) vorhanden ist — sonst gäbe es nichts, dessen
        // Einzelpreise ausgeblendet werden könnten (siehe DocumentTotals-
        // Klassendoc/Invoice::$flatRateEnabled).
        $hasRentalItems = array_filter($data['items'], static fn (array $item) => $item['isRentalItem']) !== [];
        $flatRateEnabled = $hasRentalItems && $command->flatRateEnabled;
        $flatRateAmount = $flatRateEnabled ? $data['flatRateAmount'] : null;

        // Zubehörpauschale wird beim Anlegen — sofern aktiv — vom aktuellen
        // Stand der PricingSettings auf die Rechnung SELBST gesnapshotet
        // (Snapshot-Prinzip, siehe Invoice-Klassen-Doc).
        $accessoryFlatRatePercent = $accessoryFlatRateEnabled
            ? $this->pricingSettings->get()->accessoryFlatRatePercent
            : null;

        $now = new DateTimeImmutable();
        $invoice = new Invoice(
            id: Uuid::v4(),
            customerId: $data['customerId'],
            eventLocation: $data['eventLocation'],
            venueId: $data['venueId'],
            startDate: $data['startDate'],
            endDate: $data['endDate'],
            invoiceNumber: null,
            invoiceDate: null,
            dueDate: null,
            buyerReference: $data['buyerReference'],
            status: InvoiceStatus::Draft,
            isSmallBusiness: null,
            vatRatePercent: null,
            paidAt: null,
            note: $data['note'],
            sourceOfferId: null,
            sourceRentalId: $data['rentalId'],
            createdAt: $now,
            updatedAt: $now,
            accessoryFlatRateEnabled: $accessoryFlatRateEnabled,
            accessoryFlatRatePercent: $accessoryFlatRatePercent,
            flatRateEnabled: $flatRateEnabled,
            flatRateAmount: $flatRateAmount,
            discountType: $data['discountType'],
            discountValue: $data['discountValue'],
            closingText: $this->htmlSanitizer->sanitize($command->closingText),
            logisticsMethod: $data['logisticsMethod'],
            logisticsDate1: $data['logisticsDate1'],
            logisticsDate2: $data['logisticsDate2'],
        );

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
                userId: $item['userId'],
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
