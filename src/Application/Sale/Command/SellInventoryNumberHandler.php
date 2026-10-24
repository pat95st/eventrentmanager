<?php

declare(strict_types=1);

namespace App\Application\Sale\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceItem;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Sale\Sale;
use App\Domain\Sale\SaleRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Verkauft ein einzelnes Exemplar (Inventarnummer) aus dem Bestand:
 * - legt eine eigenständige Rechnung (Entwurf) mit genau einer Position an
 *   (Menge 1, Einzelpreis = händisch angegebener Verkaufspreis) — dieselbe
 *   fortlaufende Rechnungsnummern-Sequenz wie alle anderen Rechnungen
 *   (siehe CreateInvoiceHandler), daher bewusst KEIN eigenständiges
 *   "Verkaufsbeleg"-Nummernformat;
 * - legt den zugehörigen Sale-Datensatz an (1:1 zur Inventarnummer, siehe
 *   Sale-Klassen-Doc);
 * - die Inventarnummer selbst bleibt unverändert (insbesondere wird sie
 *   NICHT gelöscht) — erst DeleteInventoryNumberHandler verweigert künftig
 *   das Löschen, und findActiveByArticle() blendet sie aus der Verfügbarkeit
 *   aus.
 *
 * $customerId ist bewusst optional (Barverkauf ohne hinterlegten Kunden,
 * siehe Produktentscheidung). Start-/Enddatum der Rechnung werden auf den
 * Verkaufszeitpunkt gesetzt (ein Verkauf hat keinen Mietzeitraum).
 */
final class SellInventoryNumberHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly CustomerRepositoryInterface $customers,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly SaleRepositoryInterface $sales,
    ) {
    }

    public function handle(CommandInterface $command): array
    {
        assert($command instanceof SellInventoryNumber);

        $inventoryNumber = $this->inventoryNumbers->findById($command->inventoryNumberId);
        if ($inventoryNumber === null) {
            throw new NotFoundException("Inventarnummer \"{$command->inventoryNumberId}\" wurde nicht gefunden.");
        }

        if ($this->sales->findByInventoryNumber($inventoryNumber->id) !== null) {
            throw new ConflictException('Diese Inventarnummer wurde bereits verkauft.');
        }

        $article = $this->articles->findById($inventoryNumber->articleId);
        if ($article === null) {
            throw new NotFoundException("Zugehöriger Artikel \"{$inventoryNumber->articleId}\" wurde nicht gefunden.");
        }

        $errors = [];

        $customerId = null;
        if ($command->customerId !== null && trim($command->customerId) !== '') {
            $customer = $this->customers->findById($command->customerId);
            if ($customer === null) {
                $errors['customerId'] = 'Der ausgewählte Kunde wurde nicht gefunden.';
            } else {
                $customerId = $customer->id;
            }
        }

        $salePrice = $this->parseRequiredAmount($command->salePrice, 'salePrice', 'Bitte einen gültigen Verkaufspreis angeben.', $errors);
        $shippingAmount = null;
        if ($command->shippingAmount !== null && trim($command->shippingAmount) !== '') {
            $shippingAmount = $this->parseRequiredAmount($command->shippingAmount, 'shippingAmount', 'Bitte einen gültigen Versandkostenbetrag angeben (oder leer lassen).', $errors);
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();

        $invoice = new Invoice(
            id: Uuid::v4(),
            customerId: $customerId,
            eventLocation: null,
            venueId: null,
            startDate: $now,
            endDate: $now,
            invoiceNumber: null,
            invoiceDate: null,
            dueDate: null,
            buyerReference: null,
            status: InvoiceStatus::Draft,
            isSmallBusiness: null,
            vatRatePercent: null,
            paidAt: null,
            note: $command->note,
            sourceOfferId: null,
            sourceRentalId: null,
            createdAt: $now,
            updatedAt: $now,
            accessoryFlatRateEnabled: false,
            accessoryFlatRatePercent: null,
            discountType: null,
            discountValue: null,
            shippingAmount: $shippingAmount,
        );
        $this->invoices->save($invoice);

        $item = new InvoiceItem(
            id: Uuid::v4(),
            invoiceId: $invoice->id,
            articleId: $article->id,
            quantity: 1,
            unitPricePerDay: $salePrice,
            lineNote: 'Verkauf Inventarnummer ' . $inventoryNumber->number,
        );
        $this->invoices->replaceItems($invoice->id, [$item]);

        $sale = new Sale(
            id: Uuid::v4(),
            inventoryNumberId: $inventoryNumber->id,
            articleId: $article->id,
            quantity: 1,
            customerId: $customerId,
            invoiceId: $invoice->id,
            salePrice: $salePrice,
            costOfGoodsSold: $inventoryNumber->effectivePurchasePrice($article->purchasePrice),
            shippingAmount: $shippingAmount,
            soldAt: $now,
            note: $command->note,
            createdAt: $now,
        );
        $this->sales->save($sale);

        return ['saleId' => $sale->id, 'invoiceId' => $invoice->id];
    }

    /** @param array<string, string> $errors */
    private function parseRequiredAmount(string $raw, string $field, string $errorMessage, array &$errors): ?string
    {
        $parsed = filter_var(str_replace(',', '.', trim($raw)), FILTER_VALIDATE_FLOAT);
        if ($parsed === false || $parsed < 0) {
            $errors[$field] = $errorMessage;

            return null;
        }

        return number_format($parsed, 2, '.', '');
    }
}
