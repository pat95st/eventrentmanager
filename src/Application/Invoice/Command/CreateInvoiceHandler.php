<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceItem;
use App\Domain\Invoice\InvoiceLaborItem;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use App\Domain\Settings\PricingSettingsRepositoryInterface;
use App\Domain\Venue\VenueRepositoryInterface;
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
            $command->customerId,
            $command->startDate,
            $command->endDate,
            $command->eventLocation,
            $command->buyerReference,
            $command->note,
            $command->items,
            $command->laborItems,
            $command->discountType,
            $command->discountValue,
        );

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        // Zubehörpauschale wird beim Anlegen — sofern aktiv — vom aktuellen
        // Stand der PricingSettings auf die Rechnung SELBST gesnapshotet
        // (Snapshot-Prinzip, siehe Invoice-Klassen-Doc).
        $accessoryFlatRatePercent = $command->accessoryFlatRateEnabled
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
            sourceRentalId: null,
            createdAt: $now,
            updatedAt: $now,
            accessoryFlatRateEnabled: $command->accessoryFlatRateEnabled,
            accessoryFlatRatePercent: $accessoryFlatRatePercent,
            discountType: $data['discountType'],
            discountValue: $data['discountValue'],
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

        return $invoice->id;
    }
}
