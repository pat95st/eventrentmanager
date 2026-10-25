<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Rental\RentalCustomerChecklistTokenRepositoryInterface;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;

/**
 * Löst einen öffentlichen Kunden-Checklisten-Link auf (siehe
 * CustomerChecklistController). Wirft NotFoundException sowohl bei einem
 * unbekannten/falschen Token als auch — laut Vorgabe "Link wird nach der
 * Rückgabe ungültig" — sobald ALLE für diese Vermietung ausgegebenen Posten
 * bereits zurückgegeben sind; beide Fälle zeigen dem Kunden dieselbe,
 * bewusst nicht unterscheidende "nicht verfügbar"-Seite (siehe
 * CustomerChecklistController::show()), um keine Rückschlüsse auf gültige
 * vs. abgelaufene Token-Werte zuzulassen.
 */
final class GetRentalCustomerChecklistHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RentalCustomerChecklistTokenRepositoryInterface $tokens,
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerRepositoryInterface $customers,
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly ArticleRepositoryInterface $articles,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ManufacturerRepositoryInterface $manufacturers,
    ) {
    }

    public function handle(QueryInterface $query): RentalCustomerChecklistView
    {
        assert($query instanceof GetRentalCustomerChecklist);

        $rawToken = trim($query->token);
        $token = $rawToken !== '' ? $this->tokens->findByToken($rawToken) : null;
        if ($token === null) {
            throw new NotFoundException('Diese Checkliste ist nicht (mehr) verfügbar.');
        }

        $rental = $this->rentals->findById($token->rentalId);
        if ($rental === null) {
            throw new NotFoundException('Diese Checkliste ist nicht (mehr) verfügbar.');
        }

        $customer = $this->customers->findById($rental->customerId);
        if ($customer === null) {
            throw new NotFoundException('Diese Checkliste ist nicht (mehr) verfügbar.');
        }

        $allIssuedItems = $this->issuedItems->findByRental($rental->id);
        $activeIssuedItems = array_values(array_filter($allIssuedItems, static fn ($item) => $item->returnedAt === null));

        // War mindestens etwas ausgegeben UND ist inzwischen alles
        // zurückgegeben: die Vermietung ist abgeschlossen, der Link wird
        // ungültig. Noch NICHTS ausgegeben (z. B. Link bereits vor der
        // Ausgabe geteilt) ist dagegen kein Abbruchgrund — dann bleibt die
        // Liste einfach (noch) leer, siehe View.
        if ($activeIssuedItems === [] && $allIssuedItems !== []) {
            throw new NotFoundException('Diese Checkliste ist nicht (mehr) verfügbar.');
        }

        // Siehe GetInvoiceHandler für die identische Begründung des
        // kleinen Hersteller-Caches.
        $manufacturerCache = [];

        $items = array_values(array_filter(array_map(
            function ($issuedItem) use (&$manufacturerCache) {
                $inventoryNumber = $this->inventoryNumbers->findById($issuedItem->inventoryNumberId);
                $article = $this->articles->findById($issuedItem->articleId);
                if ($inventoryNumber === null || $article === null) {
                    return null;
                }

                if (!array_key_exists($article->manufacturerId, $manufacturerCache)) {
                    $manufacturerCache[$article->manufacturerId] = $this->manufacturers->findById($article->manufacturerId);
                }

                return new RentalIssuedItemWithDetails($issuedItem, $inventoryNumber, $article, $manufacturerCache[$article->manufacturerId]);
            },
            $activeIssuedItems
        )));

        return new RentalCustomerChecklistView($rental, $customer, $items);
    }
}
