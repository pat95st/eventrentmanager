<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Insurance;

use App\Application\Insurance\Command\EstimateMissingCurrentValues;
use App\Application\Insurance\Command\EstimationSummary;
use App\Application\Insurance\Command\InsuranceHandler;
use App\Application\Insurance\Command\SetInventoryNumberCurrentValue;
use App\Application\Insurance\Estimation\CurrentValueEstimate;
use App\Application\Insurance\Estimation\CurrentValueEstimationRequest;
use App\Application\Insurance\Estimation\CurrentValueEstimatorInterface;
use App\Application\Insurance\Query\GetRentalInsuranceList;
use App\Application\Insurance\Query\GetRentalInsuranceListHandler;
use App\Application\Insurance\Query\RentalInsuranceList;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Application\Rental\Query\RentalDetail;
use App\Application\Rental\Query\RentalIssuedItemWithDetails;
use App\Application\Rental\Query\RentalItemWithArticle;
use App\Domain\Article\Article;
use App\Domain\Article\ArticleImage;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Category\Category;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerType;
use App\Domain\Exception\ValidationException;
use App\Domain\Insurance\CurrentValue;
use App\Domain\Insurance\CurrentValueResolver;
use App\Domain\Insurance\InsuranceField;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\Manufacturer;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalIssuedItem;
use App\Domain\Rental\RentalItem;
use App\Infrastructure\Csv\InsuranceListCsvExporter;
use App\Infrastructure\Integration\Insurance\OpenAiCurrentValueEstimator;
use App\Infrastructure\Pdf\InsuranceListPdfGenerator;
use Codeception\Test\Unit;
use DateTimeImmutable;
use Tests\Support\Fakes\InMemoryCurrentValueRepository;

/**
 * Versicherungsliste: Zusammenstellung der Zeilen, Zeitwert-Auflösung,
 * Summen, CSV-/PDF-Export und KI-Sammelschätzung (gegen Fakes).
 */
final class RentalInsuranceListTest extends Unit
{
    private InMemoryCurrentValueRepository $values;
    /** @var array<string, Article> */
    private array $articles = [];
    /** @var array<string, InventoryNumber> */
    private array $inventoryNumbers = [];
    private Manufacturer $manufacturer;
    private Category $category;
    private RentalDetail $detail;

    protected function _before(): void
    {
        $now = new DateTimeImmutable('2026-01-01');
        $this->values = new InMemoryCurrentValueRepository();
        $this->manufacturer = new Manufacturer('man-1', 'Shure', 'SHU', $now, $now);
        $this->category = new Category('cat-1', 'Mikrofone', null, $now, $now);

        $mic = $this->article('art-mic', 'SM58', '99.00', 'cat-1');
        $cable = $this->article('art-xlr', 'XLR-Kabel 10 m', null, null);
        $this->articles = ['art-mic' => $mic, 'art-xlr' => $cable];

        $this->inventoryNumbers = [
            'inv-1' => new InventoryNumber('inv-1', 'art-mic', 'SHU-SM5-0001', new DateTimeImmutable('2021-05-01'), null, null, null, null, null, $now, $now),
            'inv-2' => new InventoryNumber('inv-2', 'art-mic', 'SHU-SM5-0002', new DateTimeImmutable('2023-02-01'), null, null, '89.00', null, null, $now, $now),
            'inv-3' => new InventoryNumber('inv-3', 'art-xlr', 'SHU-XLR-0001', null, null, null, null, null, null, $now, $now),
        ];

        $rental = new Rental('rent-1', 'cust-1', new DateTimeImmutable('2026-06-01'), new DateTimeImmutable('2026-06-03'), 'Sommerfest', 'Stadtpark', null, 3, $now, $now);
        $customer = new Customer('cust-1', 'K-1', CustomerType::Privat, null, 'Erika', 'Muster', 'Weg 1', null, '12345', 'Stadt', null, null, null, $now, $now);

        $issued = [];
        foreach ($this->inventoryNumbers as $inv) {
            $issued[] = new RentalIssuedItemWithDetails(
                new RentalIssuedItem('iss-' . $inv->id, 'rent-1', $inv->articleId, $inv->id, $now),
                $inv,
                $this->articles[$inv->articleId],
                $this->manufacturer,
            );
        }
        $items = [
            new RentalItemWithArticle(new RentalItem('item-1', 'rent-1', 'art-mic', 3, false, $now, $now), $mic, 2, [], [], $this->manufacturer),
            new RentalItemWithArticle(new RentalItem('item-2', 'rent-1', 'art-xlr', 1, false, $now, $now), $cable, 1, [], [], $this->manufacturer),
        ];
        $this->detail = new RentalDetail($rental, $customer, $items, $issued);
    }

    public function testBuildsOneRowPerDeviceWithResolvedValues(): void
    {
        $this->values->setForInventoryNumber('inv-1', '60.00', CurrentValue::SOURCE_AI, 'Gebraucht');
        $this->values->setArticleFixedValue('art-xlr', '15.00');

        $list = $this->listHandler()->handle(new GetRentalInsuranceList('rent-1'));

        // 3 ausgegeben + 1 geplantes, noch nicht ausgegebenes SM58
        $this->assertCount(4, $list->rows);
        [$first, $second, $planned, $cableRow] = $list->rows;

        $this->assertSame('Mikrofone', $first->category);
        $this->assertSame('Shure SM58', $first->manufacturerModel);
        $this->assertSame('SHU-SM5-0001', $first->inventoryNumber);
        $this->assertSame(2021, $first->acquisitionYear);
        $this->assertSame('60.00', $first->currentValue);
        $this->assertSame(CurrentValueResolver::ORIGIN_AI, $first->currentValueOrigin);
        $this->assertSame('99.00', $first->purchasePrice);

        $this->assertSame('89.00', $second->purchasePrice, 'abweichender Einkaufspreis der Inventarnummer');
        $this->assertNull($second->currentValue);

        $this->assertNull($planned->inventoryNumber);
        $this->assertNull($planned->acquisitionYear);

        $this->assertNull($cableRow->category, 'ohne Kategorie zuletzt');
        $this->assertSame('15.00', $cableRow->currentValue);
        $this->assertSame(CurrentValueResolver::ORIGIN_ARTICLE, $cableRow->currentValueOrigin);
        $this->assertNull($cableRow->purchasePrice);

        $this->assertSame('75.00', $list->totalCurrentValue());
        $this->assertSame('287.00', $list->totalPurchasePrice());
        $this->assertSame(2, $list->countMissingCurrentValues());
        $this->assertSame(1, $list->countMissingPurchasePrices());
    }

    public function testCanExcludeDevicesThatWereNotIssuedYet(): void
    {
        $list = $this->listHandler()->handle(new GetRentalInsuranceList('rent-1', includeUnissued: false));

        $this->assertCount(3, $list->rows);
    }

    public function testCsvContainsOnlyTheSelectedColumnsAndLeavesMissingValuesEmpty(): void
    {
        $this->values->setForInventoryNumber('inv-1', '1234.5');
        $list = $this->listHandler()->handle(new GetRentalInsuranceList('rent-1', includeUnissued: false));

        $csv = (new InsuranceListCsvExporter())->generate($list, [InsuranceField::InventoryNumber, InsuranceField::AcquisitionYear, InsuranceField::CurrentValue]);
        $lines = explode("\n", trim(substr($csv, 3)));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertSame('Inventarnummer;Anschaffungsjahr;"Geschätzter Zeitwert"', $lines[0]);
        $this->assertSame('SHU-SM5-0001;2021;1234,50', $lines[1]);
        $this->assertSame('SHU-SM5-0002;2023;', $lines[2]);
        $this->assertSame('SHU-XLR-0001;;', $lines[3]);
    }

    public function testPdfIsGeneratedForAnySelectionOfColumns(): void
    {
        $list = $this->listHandler()->handle(new GetRentalInsuranceList('rent-1'));
        $generator = new InsuranceListPdfGenerator();

        $this->assertStringStartsWith('%PDF', $generator->generate($list, InsuranceField::cases()));
        $this->assertStringStartsWith('%PDF', $generator->generate($list, [InsuranceField::ManufacturerModel]));
        $this->assertStringStartsWith('%PDF', $generator->generate(new RentalInsuranceList($list->rental, $list->customer, []), []));
    }

    public function testEstimatesMissingValuesOnceForIdenticalDevicesAndKeepsExistingOnes(): void
    {
        $this->values->setForInventoryNumber('inv-1', '70.00');
        $estimator = $this->estimator();
        $handler = $this->insuranceHandler($estimator);

        $summary = $handler->handle(new EstimateMissingCurrentValues('rent-1'));

        $this->assertInstanceOf(EstimationSummary::class, $summary);
        $this->assertSame(2, $summary->estimated); // inv-2 und inv-3; inv-1 hat schon einen Wert
        $this->assertSame('70.00', $this->values->forInventoryNumber('inv-1')?->value);
        $this->assertTrue($this->values->forInventoryNumber('inv-2')?->isAiEstimate());
        $this->assertSame(2, $estimator->calls);
    }

    public function testDevicesWithArticleFixedValueAreNotSentToTheAi(): void
    {
        $this->values->setArticleFixedValue('art-xlr', '15.00');
        $this->values->setArticleFixedValue('art-mic', '50.00');
        $estimator = $this->estimator();

        $summary = $this->insuranceHandler($estimator)->handle(new EstimateMissingCurrentValues('rent-1'));

        $this->assertSame(0, $summary->estimated);
        $this->assertSame(0, $estimator->calls);
    }

    public function testManualValueIsValidated(): void
    {
        $handler = $this->insuranceHandler($this->estimator());
        $handler->handle(new SetInventoryNumberCurrentValue('inv-1', '149,90'));
        $this->assertSame('149.90', $this->values->forInventoryNumber('inv-1')?->value);

        $handler->handle(new SetInventoryNumberCurrentValue('inv-1', ''));
        $this->assertNull($this->values->forInventoryNumber('inv-1'));

        $this->expectException(ValidationException::class);
        $handler->handle(new SetInventoryNumberCurrentValue('inv-1', 'abc'));
    }

    public function testParsesTheAiAnswer(): void
    {
        $estimate = OpenAiCurrentValueEstimator::parseAnswer('{"current_value_eur": 64.4, "reasoning": "Gebrauchtpreis"}');
        $this->assertSame('64.40', $estimate->value);
        $this->assertSame('Gebrauchtpreis', $estimate->reasoning);

        $this->assertSame('1200.00', OpenAiCurrentValueEstimator::parseAnswer('{"current_value_eur": "1200 €"}')->value);

        $this->expectException(\App\Application\Insurance\Estimation\CurrentValueEstimationException::class);
        OpenAiCurrentValueEstimator::parseAnswer('{"current_value_eur": null}');
    }

    private function article(string $id, string $name, ?string $purchasePrice, ?string $categoryId): Article
    {
        $now = new DateTimeImmutable('2026-01-01');

        return new Article(
            id: $id,
            name: $name,
            manufacturerId: 'man-1',
            prefix: null,
            categoryId: $categoryId,
            description: null,
            weightValue: null,
            weightUnit: null,
            dimensions: null,
            is19Inch: false,
            heightUnits: null,
            installDepthCm: null,
            purchasePrice: $purchasePrice,
            requiresElectricalInspection: false,
            protectionClass: null,
            hasFirmware: false,
            isBillable: true,
            isActive: true,
            dailyRentalPrice: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function listHandler(): GetRentalInsuranceListHandler
    {
        $detail = $this->detail;
        $getRental = new class ($detail) implements QueryHandlerInterface {
            public function __construct(private readonly RentalDetail $detail)
            {
            }

            public function handle(QueryInterface $query): RentalDetail
            {
                return $this->detail;
            }
        };

        return new GetRentalInsuranceListHandler($getRental, $this->categories(), $this->values);
    }

    /** @return CurrentValueEstimatorInterface&object{calls: int} */
    private function estimator(): CurrentValueEstimatorInterface
    {
        return new class implements CurrentValueEstimatorInterface {
            public int $calls = 0;

            public function isAvailable(): bool
            {
                return true;
            }

            public function estimate(CurrentValueEstimationRequest $request): CurrentValueEstimate
            {
                $this->calls++;

                return new CurrentValueEstimate('42.00', 'Test');
            }
        };
    }

    private function insuranceHandler(CurrentValueEstimatorInterface $estimator): InsuranceHandler
    {
        $inventoryNumbers = $this->inventoryNumbers;
        $articles = $this->articles;
        $manufacturer = $this->manufacturer;

        $inventoryRepo = new class ($inventoryNumbers) implements InventoryNumberRepositoryInterface {
            public function __construct(private readonly array $items)
            {
            }

            public function findById(string $id): ?InventoryNumber
            {
                return $this->items[$id] ?? null;
            }

            public function save(InventoryNumber $inventoryNumber): void
            {
            }
            public function findByNumber(string $number): ?InventoryNumber
            {
                return null;
            }
            public function findByArticle(string $articleId): array
            {
                return [];
            }
            public function findActiveByArticle(string $articleId): array
            {
                return [];
            }
            public function countActiveByArticles(array $articleIds): array
            {
                return [];
            }
            public function findByReceipt(string $receiptId): array
            {
                return [];
            }
            public function existsByNumber(string $number, ?string $excludeId = null): bool
            {
                return false;
            }
            public function findAllWithInstalledFirmwareVersion(): array
            {
                return [];
            }
            public function existsByInstalledFirmwareVersion(string $firmwareVersionId): bool
            {
                return false;
            }
            public function delete(string $id): void
            {
            }
        };

        $articleRepo = new class ($articles) implements ArticleRepositoryInterface {
            public function __construct(private readonly array $items)
            {
            }

            public function findById(string $id): ?Article
            {
                return $this->items[$id] ?? null;
            }

            public function save(Article $article): void
            {
            }
            public function search(?string $searchTerm, ?string $manufacturerId, array $categoryIds, int $limit, int $offset, bool $onlyActive = false, array $manufacturerIds = [], bool $onlyRentableIndividually = false, bool $excludeBundles = false): array
            {
                return [];
            }
            public function countSearch(?string $searchTerm, ?string $manufacturerId, array $categoryIds, bool $onlyActive = false, array $manufacturerIds = [], bool $onlyRentableIndividually = false, bool $excludeBundles = false): int
            {
                return 0;
            }
            public function countByCategory(string $categoryId): int
            {
                return 0;
            }
            public function countByManufacturer(string $manufacturerId): int
            {
                return 0;
            }
            public function existsByPrefix(string $manufacturerId, string $prefix, ?string $excludeId = null): bool
            {
                return false;
            }
            public function delete(string $id): void
            {
            }
            public function addImage(ArticleImage $image): void
            {
            }
            public function deleteImage(string $imageId): ?ArticleImage
            {
                return null;
            }
            public function findImageById(string $imageId): ?ArticleImage
            {
                return null;
            }
        };

        $manufacturerRepo = new class ($manufacturer) implements ManufacturerRepositoryInterface {
            public function __construct(private readonly Manufacturer $manufacturer)
            {
            }

            public function findById(string $id): ?Manufacturer
            {
                return $id === $this->manufacturer->id ? $this->manufacturer : null;
            }

            public function save(Manufacturer $manufacturer): void
            {
            }
            public function findGeneric(): ?Manufacturer
            {
                return null;
            }
            public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array
            {
                return [];
            }
            public function countAll(?string $search = null): int
            {
                return 0;
            }
            public function existsByName(string $name, ?string $excludeId = null): bool
            {
                return false;
            }
            public function existsByPrefix(string $prefix, ?string $excludeId = null): bool
            {
                return false;
            }
            public function isUsedByArticles(string $id): bool
            {
                return false;
            }
            public function isUsedByCaseItems(string $id): bool
            {
                return false;
            }
            public function delete(string $id): void
            {
            }
        };

        return new InsuranceHandler($this->values, $inventoryRepo, $articleRepo, $manufacturerRepo, $this->categories(), $estimator, $this->listHandler());
    }

    private function categories(): CategoryRepositoryInterface
    {
        return new class ($this->category) implements CategoryRepositoryInterface {
            public function __construct(private readonly Category $category)
            {
            }

            public function findById(string $id): ?Category
            {
                return $id === $this->category->id ? $this->category : null;
            }

            public function save(Category $category): void
            {
            }
            public function findAll(): array
            {
                return [$this->category];
            }
            public function findChildren(?string $parentId): array
            {
                return [];
            }
            public function findDescendantIds(string $categoryId): array
            {
                return [];
            }
            public function existsByNameAndParent(string $name, ?string $parentId, ?string $excludeId = null): bool
            {
                return false;
            }
            public function delete(string $id): void
            {
            }
            public function findPowerDistributorCategoryId(): ?string
            {
                return null;
            }
            public function findCableCategoryId(): ?string
            {
                return null;
            }
        };
    }
}
