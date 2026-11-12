<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Article;

use App\Application\Article\Command\AddInventoryNumber;
use App\Application\Article\Command\AddInventoryNumberHandler;
use App\Domain\Article\Article;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Article\FirmwareVersionRepositoryInterface;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\Manufacturer;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Supplier\SupplierRepositoryInterface;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class AddInventoryNumberHandlerTest extends Unit
{
    /**
     * Regression: ist die nächste laufende Nummer bereits vergeben (z. B. durch
     * gleichzeitiges Anlegen oder abweichende Altdaten), wird sie übersprungen.
     * Früher brach das mit einem ArgumentCountError ab, weil der Platzhalter
     * für die vergebene Nummer ohne installedFirmwareVersionId erzeugt wurde.
     */
    public function testSkipsNumbersThatAreAlreadyTakenElsewhere(): void
    {
        $now = new DateTimeImmutable();
        $article = new Article(
            id: 'art-1',
            name: 'Akkuschrauber',
            manufacturerId: 'man-1',
            prefix: 'AKS',
            categoryId: null,
            description: null,
            weightValue: null,
            weightUnit: null,
            dimensions: null,
            is19Inch: false,
            heightUnits: null,
            installDepthCm: null,
            purchasePrice: null,
            requiresElectricalInspection: false,
            protectionClass: null,
            hasFirmware: false,
            isBillable: true,
            isActive: true,
            dailyRentalPrice: null,
            createdAt: $now,
            updatedAt: $now,
        );
        $existing = new InventoryNumber('inv-1', 'art-1', 'BOS-AKS-0001', null, null, null, null, null, null, $now, $now);

        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->method('findById')->willReturn($article);
        $manufacturers = $this->createMock(ManufacturerRepositoryInterface::class);
        $manufacturers->method('findById')->willReturn(new Manufacturer('man-1', 'Bosch', 'BOS', $now, $now));

        $saved = [];
        $inventoryNumbers = $this->createMock(InventoryNumberRepositoryInterface::class);
        $inventoryNumbers->method('findByArticle')->willReturn([$existing]);
        $inventoryNumbers->method('existsByNumber')->willReturnCallback(
            static fn (string $number): bool => in_array($number, ['BOS-AKS-0001', 'BOS-AKS-0002', 'BOS-AKS-0003'], true)
        );
        $inventoryNumbers->method('save')->willReturnCallback(static function (InventoryNumber $inv) use (&$saved): void {
            $saved[] = $inv;
        });

        $handler = new AddInventoryNumberHandler(
            $inventoryNumbers,
            $articles,
            $manufacturers,
            $this->createMock(SupplierRepositoryInterface::class),
            $this->createMock(ReceiptRepositoryInterface::class),
            $this->createMock(FirmwareVersionRepositoryInterface::class),
        );

        $id = $handler->handle(new AddInventoryNumber('art-1', null));

        $this->assertCount(1, $saved);
        $this->assertSame($id, $saved[0]->id);
        $this->assertSame('BOS-AKS-0004', $saved[0]->number);
    }
}
