<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleBundleComponentRepositoryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Rental\RentalBundleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceItem;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Settings\PriceTierRepositoryInterface;
use App\Domain\Settings\PricingSettingsRepositoryInterface;
use App\Domain\Settings\RentalPriceCalculator;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Erstellt eine neue Entwurfs-Rechnung aus einer bestehenden Veranstaltung
 * (Rental): Kunde, Veranstaltungsort und Zeitraum werden übernommen, ebenso
 * die geplanten Positionen (Artikel + Menge). Anders als bei einem Angebot
 * kennt RentalItem KEINEN Preis — für jede Position wird hier serverseitig
 * derselbe Preisvorschlag berechnet, den auch offers/form.php clientseitig
 * berechnet: Article::$dailyRentalPrice * Preisstaffel-Prozentsatz für die
 * tatsächliche Einsatztage-Anzahl der Veranstaltung (RentalPriceCalculator,
 * PriceTierRepositoryInterface), begrenzt auf die höchste bekannte Stufe
 * (Invoice::deploymentDaysForPricing()). Artikel ohne hinterlegten
 * Tagesmietpreis (z. B. nicht abrechenbares Zubehör) werden mit
 * unitPricePerDay = "0.00" übernommen, statt die Umwandlung abzubrechen —
 * der Preis bleibt auf der Rechnung frei nachträglich änderbar, solange sie
 * Entwurf ist.
 *
 * source_rental_id wird gesetzt. Eine Veranstaltung kann beliebig oft in
 * Rechnungen umgewandelt werden (z. B. Teilrechnungen).
 */
final class CreateInvoiceFromRentalHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly RentalItemRepositoryInterface $rentalItems,
        private readonly ArticleRepositoryInterface $articles,
        private readonly PriceTierRepositoryInterface $priceTiers,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly PricingSettingsRepositoryInterface $pricingSettings,
        // Bundles (Migration 107): gebuchte Bundles werden zum Bundle-Preis
        // abgerechnet, ihre Bestandteile nicht noch einmal einzeln.
        private readonly ?RentalBundleRepositoryInterface $rentalBundles = null,
        private readonly ?ArticleBundleComponentRepositoryInterface $bundleComponents = null,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateInvoiceFromRental);

        $rental = $this->rentals->findById($command->rentalId);
        if ($rental === null) {
            throw new NotFoundException("Veranstaltung \"{$command->rentalId}\" wurde nicht gefunden.");
        }

        $rentalItems = $this->rentalItems->findByRental($rental->id);
        if ($rentalItems === []) {
            throw new ValidationException(['items' => 'Diese Veranstaltung enthält keine geplanten Artikel und kann daher nicht in eine Rechnung umgewandelt werden.']);
        }

        $now = new DateTimeImmutable();
        $pricingSettings = $this->pricingSettings->get();

        $invoice = new Invoice(
            id: Uuid::v4(),
            customerId: $rental->customerId,
            eventLocation: $rental->eventLocation,
            venueId: $rental->venueId,
            startDate: $rental->startDate,
            endDate: $rental->endDate,
            invoiceNumber: null,
            invoiceDate: null,
            dueDate: null,
            buyerReference: null,
            status: InvoiceStatus::Draft,
            isSmallBusiness: null,
            vatRatePercent: null,
            paidAt: null,
            note: null,
            sourceOfferId: null,
            sourceRentalId: $rental->id,
            createdAt: $now,
            updatedAt: $now,
            // Zubehörpauschale gilt für JEDE neue Rechnung (auch aus einer
            // Veranstaltung erzeugte), standardmäßig aktiv (siehe
            // Aufgabenstellung); der aktuelle Prozentsatz wird gesnapshotet.
            accessoryFlatRateEnabled: true,
            accessoryFlatRatePercent: $pricingSettings->accessoryFlatRatePercent,
            // Abhol-/Rückgabetermin aus der Logistik-Zuordnung der
            // Veranstaltung vorbelegen (siehe Rental::$pickupAt/$returnAt),
            // sofern dort gepflegt — bleibt auf der Rechnung danach frei
            // editierbar und unabhängig von der Veranstaltung (siehe
            // Invoice::$logisticsMethod-Klassendoc). Default-Methode
            // 'pickup' (Selbstabholung); eine Lieferung durch uns wird hier
            // nicht automatisch erkannt und muss auf der Rechnung selbst
            // umgestellt werden.
            logisticsMethod: ($rental->pickupAt !== null || $rental->returnAt !== null) ? 'pickup' : null,
            logisticsDate1: $rental->pickupAt,
            logisticsDate2: $rental->returnAt,
        );
        $this->invoices->save($invoice);

        // Einsatztage der Veranstaltung -> Preisstaffel-Stufe (1..7), analog
        // Invoice::deploymentDaysForPricing()/Offer::deploymentDaysForPricing().
        $deploymentDays = min(max($rental->maxDeploymentDays(), 1), 7);
        $tiers = $this->priceTiers->getAll();

        $priceFor = function ($article) use ($tiers, $pricingSettings, $deploymentDays): string {
            if (!$article->isBillable || $article->dailyRentalPrice === null) {
                return '0.00';
            }
            $pricesByDay = RentalPriceCalculator::calculate(
                $article->dailyRentalPrice,
                $tiers,
                $pricingSettings->priceRoundingStep,
                $pricingSettings->priceRoundingDirection,
            );

            return $pricesByDay[$deploymentDays] ?? $article->dailyRentalPrice;
        };

        $items = [];

        // Gebuchte Bundles zuerst, je eine Position zum Bundle-Preis; die
        // dadurch belegten Bestandteil-Mengen werden unten abgezogen.
        $coveredByBundles = [];
        foreach ($this->rentalBundles?->findByRental($rental->id) ?? [] as $rentalBundle) {
            $bundleArticle = $this->articles->findById($rentalBundle->bundleArticleId);
            if ($bundleArticle === null) {
                continue;
            }
            foreach ($this->bundleComponents?->findByBundle($bundleArticle->id) ?? [] as $component) {
                $coveredByBundles[$component->componentArticleId] = ($coveredByBundles[$component->componentArticleId] ?? 0)
                    + $component->quantity * $rentalBundle->quantity;
            }
            $items[] = new InvoiceItem(
                id: Uuid::v4(),
                invoiceId: $invoice->id,
                articleId: $bundleArticle->id,
                quantity: $rentalBundle->quantity,
                unitPricePerDay: $priceFor($bundleArticle),
                lineNote: null,
                sortOrder: count($items),
            );
        }

        foreach ($rentalItems as $rentalItem) {
            $article = $this->articles->findById($rentalItem->articleId);
            if ($article === null) {
                continue;
            }

            $quantity = $rentalItem->quantity - ($coveredByBundles[$rentalItem->articleId] ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            $items[] = new InvoiceItem(
                id: Uuid::v4(),
                invoiceId: $invoice->id,
                articleId: $rentalItem->articleId,
                quantity: $quantity,
                unitPricePerDay: $priceFor($article),
                lineNote: null,
                sortOrder: count($items),
            );
        }

        $this->invoices->replaceItems($invoice->id, $items);

        return $invoice->id;
    }
}
