<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Article\FirmwareVersionRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;
use App\Shared\Money;
use DateTimeImmutable;

final class UpdateInventoryNumberHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly SupplierRepositoryInterface $suppliers,
        private readonly ReceiptRepositoryInterface $receipts,
        private readonly ArticleRepositoryInterface $articles,
        private readonly FirmwareVersionRepositoryInterface $firmwareVersions,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateInventoryNumber);

        $inventoryNumber = $this->inventoryNumbers->findById($command->id);
        if ($inventoryNumber === null) {
            throw new NotFoundException("Inventurnummer \"{$command->id}\" wurde nicht gefunden.");
        }

        $article = $this->articles->findById($inventoryNumber->articleId);
        if ($article === null) {
            throw new NotFoundException('Artikel dieser Inventurnummer wurde nicht gefunden.');
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

        $inventoryNumber->acquisitionDate = $command->acquisitionDate !== null && $command->acquisitionDate !== ''
            ? new DateTimeImmutable($command->acquisitionDate)
            : null;
        $receiptId = $command->receiptId !== null && $command->receiptId !== '' ? $command->receiptId : null;
        // Siehe AddInventoryNumberHandler: ist ein Beleg verknüpft, wird der Lieferant
        // ausschließlich über den Beleg aufgelöst, ein übermittelter supplier_id-Wert
        // wird dann ignoriert und NICHT gespeichert.
        $inventoryNumber->supplierId = $receiptId === null && $command->supplierId !== null && $command->supplierId !== ''
            ? $command->supplierId
            : null;
        $inventoryNumber->receiptId = $receiptId;
        $inventoryNumber->purchasePriceOverride = $purchasePriceOverride;
        $inventoryNumber->storageLocation = $storageLocation;
        $inventoryNumber->installedFirmwareVersionId = $installedFirmwareVersionId;
        $inventoryNumber->updatedAt = new DateTimeImmutable();

        $this->inventoryNumbers->save($inventoryNumber);

        return null;
    }

    /** Siehe AddInventoryNumberHandler::resolveInstalledFirmwareVersionId(). */
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
