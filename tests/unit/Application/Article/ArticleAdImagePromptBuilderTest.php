<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Article;

use App\Application\Article\Command\ArticleAdImagePromptBuilder;
use App\Domain\Article\Article;
use App\Domain\Category\Category;
use App\Domain\Manufacturer\Manufacturer;
use App\Domain\Rack\RackWidth;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class ArticleAdImagePromptBuilderTest extends Unit
{
    public function testDefaultPromptForbidsTextInImage(): void
    {
        $prompt = (new ArticleAdImagePromptBuilder())->build($this->article(), $this->manufacturer(), null, false, false, false, null);

        $this->assertStringContainsString('kein Text', $prompt);
        $this->assertStringNotContainsString('Text-Overlay', $prompt);
    }

    public function testIncludesDisplayNameWhenRequested(): void
    {
        $prompt = (new ArticleAdImagePromptBuilder())->build($this->article(), $this->manufacturer(), null, true, true, false, null);

        $this->assertStringContainsString('Text-Overlay', $prompt);
        $this->assertStringContainsString('"Yamaha CL5"', $prompt);
        $this->assertStringNotContainsString('Eigenschaften', $prompt);
        $this->assertStringNotContainsString('kein Text', $prompt);
    }

    public function testIncludesPropertiesWhenRequested(): void
    {
        $now = new DateTimeImmutable();
        $category = new Category('cat-1', 'Mischpulte', null, $now, $now);

        $prompt = (new ArticleAdImagePromptBuilder())->build($this->article(), $this->manufacturer(), $category, false, false, true, 'Blauer Hintergrund');

        $this->assertStringContainsString('"Hersteller: Yamaha"', $prompt);
        $this->assertStringContainsString('"Kategorie: Mischpulte"', $prompt);
        $this->assertStringContainsString('"Gewicht: 36,5 kg"', $prompt);
        $this->assertStringContainsString('"Maße: 1053 x 299 x 667 mm"', $prompt);
        $this->assertStringContainsString('"Rackeinbau: 19 Zoll, 3 HE"', $prompt);
        $this->assertStringNotContainsString('Miete', $prompt);
        $this->assertStringNotContainsString('Produktbezeichnung', $prompt);
        $this->assertStringEndsWith('Zusätzliche Anweisungen: Blauer Hintergrund', $prompt);
    }

    public function testIncludesPriceOnlyWhenRequested(): void
    {
        $builder = new ArticleAdImagePromptBuilder();

        $withPrice = $builder->build($this->article(), $this->manufacturer(), null, false, false, false, null, true);
        $this->assertStringContainsString('Text-Overlay', $withPrice);
        $this->assertStringContainsString('"Miete: 1.250,00 € pro Tag"', $withPrice);
        $this->assertStringNotContainsString('Eigenschaften', $withPrice);

        $withoutPrice = $builder->build($this->article(), $this->manufacturer(), null, false, false, true, null, false);
        $this->assertStringNotContainsString('Miete', $withoutPrice);
    }

    public function testPriceIsSkippedWhenArticleHasNoPrice(): void
    {
        $prompt = (new ArticleAdImagePromptBuilder())->build($this->article(billable: false), $this->manufacturer(), null, false, false, false, null, true);

        $this->assertStringContainsString('kein Text', $prompt);
        $this->assertStringNotContainsString('Miete', $prompt);
    }

    public function testMultipleReferenceImagesAreDescribedAsViewsOfTheSameProduct(): void
    {
        $builder = new ArticleAdImagePromptBuilder();

        $multi = $builder->build($this->article(), $this->manufacturer(), null, true, false, false, null, false, 3);
        $this->assertStringContainsString('diesen 3 Produktfotos', $multi);
        $this->assertStringContainsString('verschiedenen Blickwinkeln', $multi);

        $single = $builder->build($this->article(), $this->manufacturer(), null, true, false, false, null, false, 1);
        $this->assertStringContainsString('dieses Produktfoto', $single);
    }

    public function testSkipsEmptyPropertiesAndGenericManufacturer(): void
    {
        $now = new DateTimeImmutable();
        $article = $this->article(weight: null, dimensions: null, is19Inch: false, billable: false);
        $generic = new Manufacturer('man-2', 'Generisch', null, $now, $now, true);

        $lines = (new ArticleAdImagePromptBuilder())->propertyLines($article, $generic, null);

        $this->assertSame([], $lines);
    }

    private function manufacturer(): Manufacturer
    {
        $now = new DateTimeImmutable();

        return new Manufacturer('man-1', 'Yamaha', 'YAM', $now, $now);
    }

    private function article(?string $weight = '36.500', ?string $dimensions = '1053 x 299 x 667 mm', bool $is19Inch = true, bool $billable = true): Article
    {
        $now = new DateTimeImmutable();

        return new Article(
            id: 'art-1',
            name: 'CL5',
            manufacturerId: 'man-1',
            prefix: 'CL5',
            categoryId: 'cat-1',
            description: null,
            weightValue: $weight,
            weightUnit: $weight !== null ? 'kg' : null,
            dimensions: $dimensions,
            is19Inch: $is19Inch,
            heightUnits: $is19Inch ? 3 : null,
            installDepthCm: null,
            purchasePrice: null,
            requiresElectricalInspection: false,
            protectionClass: null,
            hasFirmware: false,
            isBillable: $billable,
            isActive: true,
            dailyRentalPrice: $billable ? '1250.00' : null,
            createdAt: $now,
            updatedAt: $now,
            rackWidth: RackWidth::Full,
        );
    }
}
