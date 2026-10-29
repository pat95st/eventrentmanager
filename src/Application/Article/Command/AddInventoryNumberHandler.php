<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Article\FirmwareVersionRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\InventoryNumber\InventoryNumberGenerator;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Shared\Money;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Vergibt eine standardisierte Inventurnummer nach dem Schema
 * (Hersteller-Präfix)-(Artikel-Präfix)-(laufende Nummer), z. B. "BOS-AKS-0001".
 * Die Nummer wird bewusst nicht frei eingegeben, sondern aus den beim
 * Hersteller bzw. Artikel hinterlegten Präfixen automatisch gebildet.
 */
final class AddInventoryNumberHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly ReceiptRepositoryInterface $receipts,
        private readonly FirmwareVersionRepositoryInterface $firmwareVersions,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddInventoryNumber);

        $article = $this->articles->findById($command->articleId);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$command->articleId}\" wurde nicht gefunden.");
        }

        // Ein Bundle (Migration 107) hat keinen eigenen physischen Bestand —
        // Inventarnummern gehören an seine Bestandteile.
        if ($article->isBundle) {
            throw new ValidationException(['number' => 'Ein Bundle hat keine eigenen Inventarnummern — bitte die Bestandteile inventarisieren.']);
        }

        $installedFirmwareVersionId = $this->resolveInstalledFirmwareVersionId($article->id, $article->hasFirmware, $command->installedFirmwareVersionId);

        if ($command->supplierId !== null && $command->supplierId !== '' && $this->suppliers->findById($command->supplierId) === null) {
            throw new ValidationException(['supplierId' => 'Bitte einen gültigen Lieferanten auswählen.']);
        }

        if ($command->receiptId !== null && $command->receiptId !== '' && $this->receipts->findById($command->receiptId) === null) {
            throw new ValidationException(['receiptId' => 'Bitte einen gültigen Beleg auswählen.']);
        }

        $purchasePriceOverride = Money::normalize($command->purchasePriceOverride);
        if ($purchasePriceOverride === false) {
            throw new ValidationException(['purchasePriceOverride' => 'Bitte einen gültigen Einkaufspreis angeben (z. B. 199.99).']);
        }

        $storageLocation = $command->storageLocation !== null && trim($command->storageLocation) !== ''
            ? trim($command->storageLocation)
            : null;
        if ($storageLocation !== null && mb_strlen($storageLocation) > 255) {
            throw new ValidationException(['storageLocation' => 'Der Lagerort darf höchstens 255 Zeichen lang sein.']);
        }

        $manufacturer = $this->manufacturers->findById($article->manufacturerId);
        if ($manufacturer === null) {
            throw new NotFoundException('Hersteller des Artikels wurde nicht gefunden.');
        }

        if ($manufacturer->prefix === null || $article->prefix === null) {
            throw new ValidationException([
                'number' => 'Für diesen Artikel fehlt noch das Hersteller- und/oder Artikel-Präfix. '
                    . 'Bitte zuerst beim Hersteller und beim Artikel ein Präfix hinterlegen.',
            ]);
        }

        $existing = $this->inventoryNumbers->findByArticle($article->id);
        $number = InventoryNumberGenerator::nextNumber($existing, $manufacturer->prefix, $article->prefix);

        // Sicherheitsnetz gegen einen sehr unwahrscheinlichen Kollisionsfall
        // (z. B. gleichzeitiges Anlegen oder manuell abweichende Altdaten).
        while ($this->inventoryNumbers->existsByNumber($number)) {
            $existing[] = new InventoryNumber(
                id: 'temp',
                articleId: $article->id,
                number: $number,
                acquisitionDate: null,
                supplierId: null,
                receiptId: null,
                purchasePriceOverride: null,
                storageLocation: null,
                createdAt: new DateTimeImmutable(),
                updatedAt: new DateTimeImmutable(),
            );
            $number = InventoryNumberGenerator::nextNumber($existing, $manufacturer->prefix, $article->prefix);
        }

        $acquisitionDate = $command->acquisitionDate !== null && $command->acquisitionDate !== ''
            ? new DateTimeImmutable($command->acquisitionDate)
            : null;
        $receiptId = $command->receiptId !== null && $command->receiptId !== '' ? $command->receiptId : null;
        // Ist ein Beleg verknüpft, wird der Lieferant AUSSCHLIESSLICH über diesen Beleg
        // aufgelöst (siehe InventoryNumber::$receiptId) — ein zusätzlich übermittelter
        // supplier_id-Wert wird dann bewusst ignoriert, um keine zwei Quellen der Wahrheit
        // zu haben, die auseinanderlaufen könnten.
        $supplierId = $receiptId === null && $command->supplierId !== null && $command->supplierId !== ''
            ? $command->supplierId
            : null;

        $now = new DateTimeImmutable();
        $inventoryNumber = new InventoryNumber(
            id: Uuid::v4(),
            articleId: $command->articleId,
            number: $number,
            acquisitionDate: $acquisitionDate,
            supplierId: $supplierId,
            receiptId: $receiptId,
            purchasePriceOverride: $purchasePriceOverride,
            storageLocation: $storageLocation,
            installedFirmwareVersionId: $installedFirmwareVersionId,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->inventoryNumbers->save($inventoryNumber);

        return $inventoryNumber->id;
    }

    /**
     * Die installierte Firmware-Version ist bewusst eine Auswahl aus dem
     * Katalog des zugehörigen Artikels (kein Freitext, siehe
     * InventoryNumber::$installedFirmwareVersionId) — hier wird geprüft,
     * dass die übermittelte ID tatsächlich zu diesem Artikel gehört. Hat der
     * Artikel keine Firmware (hasFirmware = false), wird der Wert ignoriert.
     */
    private function resolveInstalledFirmwareVersionId(string $articleId, bool $hasFirmware, ?string $raw): ?string
    {
        if (!$hasFirmware || $raw === null || $raw === '') {
            return null;
        }

        $firmwareVersion = $this->firmwareVersions->findById($raw);
        if ($firmwareVersion === null || $firmwareVersion->articleId !== $articleId) {
            throw new ValidationException(['installedFirmwareVersionId' => 'Bitte eine gültige Firmware-Version dieses Artikels auswählen.']);
        }

        return $firmwareVersion->id;
    }
}
