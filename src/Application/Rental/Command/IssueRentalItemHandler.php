<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleAccessoryRepositoryInterface;
use App\Domain\Article\ArticlePreIssueCheckRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Rental\RentalIssuedItem;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Sale\SaleRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Ausgabe-Checkliste: erfasst, dass eine konkrete, per Inventarnummer
 * identifizierte physische Einheit für diese Vermietung tatsächlich
 * ausgegeben wurde. Nur Inventarnummern von Artikeln, die für diese
 * Vermietung geplant sind (RentalItem), und nur bis zur geplanten Menge.
 */
final class IssueRentalItemHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly RentalItemRepositoryInterface $items,
        private readonly RentalRepositoryInterface $rentals,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleAccessoryRepositoryInterface $accessories,
        private readonly ArticlePreIssueCheckRepositoryInterface $preIssueChecks,
        private readonly SaleRepositoryInterface $sales,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof IssueRentalItem);

        $rental = $this->rentals->findById($command->rentalId);
        if ($rental === null) {
            throw new NotFoundException("Vermietung \"{$command->rentalId}\" wurde nicht gefunden.");
        }

        $inventoryNumber = $this->inventoryNumbers->findById($command->inventoryNumberId);
        if ($inventoryNumber === null) {
            throw new NotFoundException("Inventurnummer \"{$command->inventoryNumberId}\" wurde nicht gefunden.");
        }

        // Blockierende Durchsetzung (exaktes Vorbild: die Zubehör-
        // Vollständigkeitsprüfung unten) — siehe „Vermietbedingungen-
        // Unterschrift & Ausweisprüfung“ in architektur-und-setup.md.
        // Beide Prüfungen gelten für die gesamte Vermietung bzw. den
        // gesamten Kunden, nicht nur für den aktuell ausgegebenen Posten.
        if ($rental->termsSignedAt === null) {
            throw new ValidationException([
                'inventoryNumberId' => 'Die Vermietbedingungen müssen zuerst auf dieser Seite unterschrieben werden, bevor Material ausgegeben werden kann.',
            ]);
        }

        $customer = $this->customers->findById($rental->customerId);
        if ($customer === null) {
            throw new NotFoundException("Kunde \"{$rental->customerId}\" wurde nicht gefunden.");
        }

        if ($customer->idCheckedAt === null) {
            throw new ValidationException([
                'inventoryNumberId' => 'Bei diesem Kunden muss zuerst ein Ausweisdokument geprüft und bestätigt werden, bevor Material ausgegeben werden kann.',
            ]);
        }

        // Harte Sperre, unabhängig vom Ausgabeweg (Dropdown ODER
        // Barcode-Scanner laufen beide über diesen Handler): ein verkauftes
        // Exemplar darf nie mehr vermietet werden.
        if ($this->sales->findByInventoryNumber($command->inventoryNumberId) !== null) {
            throw new ValidationException([
                'inventoryNumberId' => 'Diese Inventarnummer wurde verkauft und steht daher nicht mehr für die Vermietung zur Verfügung.',
            ]);
        }

        $rentalItem = $this->items->findByRentalAndArticle($command->rentalId, $inventoryNumber->articleId);
        if ($rentalItem === null) {
            throw new ValidationException([
                'inventoryNumberId' => 'Der Artikel dieser Inventarnummer ist für diese Vermietung nicht eingeplant.',
            ]);
        }

        // Hat der Artikel Zubehör, muss dessen Vollständigkeit erst per Modal
        // bestätigt worden sein (siehe ConfirmRentalItemAccessoriesHandler) —
        // gilt sowohl für die Dropdown- als auch die Barcode-Scanner-Ausgabe.
        if (!$rentalItem->accessoryChecked && $this->accessories->findByArticle($inventoryNumber->articleId) !== []) {
            throw new ValidationException([
                'inventoryNumberId' => 'Bitte zuerst die Vollständigkeit des Zubehörs für diesen Artikel prüfen und bestätigen.',
            ]);
        }

        // Hat der Artikel Vor-Ausgabe-Checks (siehe ArticlePreIssueCheck, z. B.
        // "Default Show bei Digitalpult geladen"), müssen diese erst per Modal
        // bestätigt worden sein — exakt analoge, unabhängige Durchsetzung zur
        // Zubehör-Vollständigkeitsprüfung oben (siehe
        // ConfirmRentalItemPreIssueChecksHandler).
        if (!$rentalItem->preIssueChecksConfirmed && $this->preIssueChecks->findByArticle($inventoryNumber->articleId) !== []) {
            throw new ValidationException([
                'inventoryNumberId' => 'Bitte zuerst die Vor-Ausgabe-Checks für diesen Artikel prüfen und bestätigen.',
            ]);
        }

        $alreadyIssued = $this->issuedItems->countByRentalAndArticle($command->rentalId, $inventoryNumber->articleId);
        if ($alreadyIssued >= $rentalItem->quantity) {
            throw new ValidationException([
                'inventoryNumberId' => "Es sind bereits alle geplanten {$rentalItem->quantity} Exemplare dieses Artikels ausgegeben.",
            ]);
        }

        if ($this->issuedItems->isInventoryNumberIssued($command->inventoryNumberId)) {
            throw new ValidationException([
                'inventoryNumberId' => 'Diese Inventarnummer ist bereits (in dieser oder einer anderen Vermietung) als ausgegeben erfasst.',
            ]);
        }

        if ($this->issuedItems->isInventoryNumberIssuedDuringRange($command->inventoryNumberId, $rental->startDate, $rental->endDate, $command->rentalId)) {
            throw new ValidationException([
                'inventoryNumberId' => 'Diese Inventarnummer ist im gewählten Zeitraum bereits einer anderen Vermietung zugeordnet.',
            ]);
        }

        $issuedItem = new RentalIssuedItem(
            id: Uuid::v4(),
            rentalId: $command->rentalId,
            articleId: $inventoryNumber->articleId,
            inventoryNumberId: $command->inventoryNumberId,
            issuedAt: new DateTimeImmutable(),
        );

        $this->issuedItems->save($issuedItem);

        return $issuedItem->id;
    }
}
