<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Article;

use App\Application\Article\Command\DeleteArticleAdImage;
use App\Application\Article\Command\DeleteArticleAdImageHandler;
use App\Domain\Article\ArticleAdImage;
use App\Domain\Article\ArticleAdImageFormat;
use App\Domain\Article\ArticleAdImageRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class DeleteArticleAdImageHandlerTest extends Unit
{
    private string $baseDirectory;

    protected function _before(): void
    {
        $this->baseDirectory = sys_get_temp_dir() . '/ad-image-test-' . bin2hex(random_bytes(4));
        mkdir($this->baseDirectory . '/article-ad-images/article-1', 0777, true);
    }

    protected function _after(): void
    {
        @unlink($this->baseDirectory . '/article-ad-images/article-1/image.png');
        @rmdir($this->baseDirectory . '/article-ad-images/article-1');
        @rmdir($this->baseDirectory . '/article-ad-images');
        @rmdir($this->baseDirectory);
    }

    public function testDeletesRecordAndStoredFile(): void
    {
        $filePath = $this->baseDirectory . '/article-ad-images/article-1/image.png';
        file_put_contents($filePath, 'png');

        $repository = $this->repositoryWith(new ArticleAdImage(
            'ad-1',
            'article-1',
            'article-1/image.png',
            ArticleAdImageFormat::Square,
            'prompt',
            null,
            new DateTimeImmutable(),
        ));

        $handler = new DeleteArticleAdImageHandler($repository, new LocalFileStorage($this->baseDirectory));
        $handler->handle(new DeleteArticleAdImage('ad-1'));

        $this->assertNull($repository->findById('ad-1'));
        $this->assertFileDoesNotExist($filePath);
    }

    public function testThrowsWhenImageDoesNotExist(): void
    {
        $handler = new DeleteArticleAdImageHandler($this->repositoryWith(), new LocalFileStorage($this->baseDirectory));

        $this->expectException(NotFoundException::class);
        $handler->handle(new DeleteArticleAdImage('missing'));
    }

    private function repositoryWith(ArticleAdImage ...$images): ArticleAdImageRepositoryInterface
    {
        return new class ($images) implements ArticleAdImageRepositoryInterface {
            /** @var array<string, ArticleAdImage> */
            private array $images = [];

            /** @param ArticleAdImage[] $images */
            public function __construct(array $images)
            {
                foreach ($images as $image) {
                    $this->images[$image->id] = $image;
                }
            }

            public function save(ArticleAdImage $image): void
            {
                $this->images[$image->id] = $image;
            }

            public function findById(string $id): ?ArticleAdImage
            {
                return $this->images[$id] ?? null;
            }

            public function findByArticle(string $articleId): array
            {
                return array_values(array_filter($this->images, fn (ArticleAdImage $i) => $i->articleId === $articleId));
            }

            public function delete(string $id): void
            {
                unset($this->images[$id]);
            }
        };
    }
}
