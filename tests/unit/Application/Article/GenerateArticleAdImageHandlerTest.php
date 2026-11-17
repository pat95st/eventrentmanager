<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Article;

use App\Application\Article\Command\GenerateArticleAdImage;
use App\Application\Article\Command\GenerateArticleAdImageHandler;
use App\Domain\Article\Article;
use App\Domain\Article\ArticleAdImageFormat;
use App\Domain\Article\ArticleAdImageRepositoryInterface;
use App\Domain\Article\ArticleImage;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Settings\AiAdImageSettings;
use App\Domain\Settings\AiAdImageSettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Integration\OpenAi\OpenAiImageClient;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * Covers the reference image checks that run before the image model is
 * called (the OpenAI client performs real HTTP requests and is not exercised).
 */
final class GenerateArticleAdImageHandlerTest extends Unit
{
    private string $storageDir;

    protected function _before(): void
    {
        $this->storageDir = sys_get_temp_dir() . '/generate-ad-image-' . bin2hex(random_bytes(4));
        mkdir($this->storageDir . '/articles/art-1', 0775, true);
        // A GIF is not accepted by the images/edits endpoint.
        file_put_contents($this->storageDir . '/articles/art-1/img-1.gif', base64_decode('R0lGODlhAQABAAAAACw=', true));
    }

    protected function _after(): void
    {
        exec('rm -rf ' . escapeshellarg($this->storageDir));
    }

    public function testGalleryReferenceRequiresGalleryImages(): void
    {
        $this->expectValidationError('useGalleryImages', $this->handler([]), true, null);
    }

    public function testGalleryReferenceSkipsUnsupportedFormats(): void
    {
        $gif = new ArticleImage('img-1', 'art-1', 'art-1/img-1.gif', 0, new DateTimeImmutable());

        $this->expectValidationError('useGalleryImages', $this->handler([$gif]), true, null);
    }

    public function testUnknownSelectedPhotoIsRejected(): void
    {
        $this->expectValidationError('sourceArticleImageId', $this->handler([]), true, 'missing');
    }

    private function expectValidationError(string $field, GenerateArticleAdImageHandler $handler, bool $useGallery, ?string $sourceId): void
    {
        try {
            $handler->handle(new GenerateArticleAdImage(
                articleId: 'art-1',
                format: ArticleAdImageFormat::cases()[0]->value,
                sourceArticleImageId: $sourceId,
                additionalInstructions: null,
                useGalleryImages: $useGallery,
            ));
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    /** @param list<ArticleImage> $images */
    private function handler(array $images): GenerateArticleAdImageHandler
    {
        $now = new DateTimeImmutable();
        $article = new Article(
            id: 'art-1',
            name: 'CL5',
            manufacturerId: null,
            prefix: 'CL5',
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
            images: $images,
        );

        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->method('findById')->willReturn($article);
        $settings = $this->createMock(AiAdImageSettingsRepositoryInterface::class);
        $settings->method('get')->willReturn(new AiAdImageSettings(openaiApiKey: 'sk-test'));

        return new GenerateArticleAdImageHandler(
            $articles,
            $this->createMock(ManufacturerRepositoryInterface::class),
            $this->createMock(CategoryRepositoryInterface::class),
            $this->createMock(ArticleAdImageRepositoryInterface::class),
            new LocalFileStorage($this->storageDir),
            $settings,
            new OpenAiImageClient(),
        );
    }
}
