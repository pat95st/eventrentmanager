<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Article;

use App\Application\Article\Command\AddArticleAdImageToGallery;
use App\Application\Article\Command\AddArticleAdImageToGalleryHandler;
use App\Domain\Article\Article;
use App\Domain\Article\ArticleAdImage;
use App\Domain\Article\ArticleAdImageFormat;
use App\Domain\Article\ArticleAdImageRepositoryInterface;
use App\Domain\Article\ArticleImage;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class AddArticleAdImageToGalleryHandlerTest extends Unit
{
    private string $storageDir;

    protected function _before(): void
    {
        $this->storageDir = sys_get_temp_dir() . '/ad-to-gallery-' . bin2hex(random_bytes(4));
        mkdir($this->storageDir . '/article-ad-images/article-1', 0775, true);
        file_put_contents($this->storageDir . '/article-ad-images/article-1/ad-1.png', 'png-bytes');
    }

    protected function _after(): void
    {
        exec('rm -rf ' . escapeshellarg($this->storageDir));
    }

    public function testCopiesAdImageToTheEndOfTheGallery(): void
    {
        $articles = $this->createMock(ArticleRepositoryInterface::class);
        $articles->method('findById')->willReturn($this->article());
        $added = null;
        $articles->expects($this->once())->method('addImage')->willReturnCallback(function (ArticleImage $image) use (&$added): void {
            $added = $image;
        });

        $handler = new AddArticleAdImageToGalleryHandler($this->adImages(), $articles, new LocalFileStorage($this->storageDir));
        $id = $handler->handle(new AddArticleAdImageToGallery('ad-1'));

        $this->assertInstanceOf(ArticleImage::class, $added);
        $this->assertSame($id, $added->id);
        $this->assertSame('article-1', $added->articleId);
        $this->assertSame(1, $added->position);
        $this->assertSame('article-1/' . $id . '.png', $added->filePath);
        $this->assertSame('png-bytes', file_get_contents($this->storageDir . '/articles/' . $added->filePath));
        // The ad image itself is kept.
        $this->assertFileExists($this->storageDir . '/article-ad-images/article-1/ad-1.png');
    }

    public function testUnknownAdImageIsRejected(): void
    {
        $handler = new AddArticleAdImageToGalleryHandler($this->adImages(), $this->createMock(ArticleRepositoryInterface::class), new LocalFileStorage($this->storageDir));

        $this->expectException(NotFoundException::class);
        $handler->handle(new AddArticleAdImageToGallery('missing'));
    }

    private function article(): Article
    {
        $now = new DateTimeImmutable();

        return new Article(
            id: 'article-1',
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
            images: [new ArticleImage('img-1', 'article-1', 'article-1/img-1.jpg', 0, $now)],
        );
    }

    private function adImages(): ArticleAdImageRepositoryInterface
    {
        $repository = $this->createMock(ArticleAdImageRepositoryInterface::class);
        $repository->method('findById')->willReturnCallback(static fn (string $id): ?ArticleAdImage => $id === 'ad-1'
            ? new ArticleAdImage(
                id: 'ad-1',
                articleId: 'article-1',
                filePath: 'article-1/ad-1.png',
                format: ArticleAdImageFormat::cases()[0],
                prompt: 'prompt',
                sourceArticleImageId: null,
                createdAt: new DateTimeImmutable(),
            )
            : null);

        return $repository;
    }
}
