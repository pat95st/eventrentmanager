<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Article;

use App\Application\Article\Command\RefineArticleAdImage;
use App\Application\Article\Command\RefineArticleAdImageHandler;
use App\Domain\Article\ArticleAdImage;
use App\Domain\Article\ArticleAdImageFormat;
use App\Domain\Article\ArticleAdImageRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\AiAdImageSettings;
use App\Domain\Settings\AiAdImageSettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Integration\OpenAi\OpenAiImageClient;
use Codeception\Test\Unit;
use DateTimeImmutable;

/**
 * Covers the checks that run before the image model is called (the OpenAI
 * client itself performs real HTTP requests and is not exercised here).
 */
final class RefineArticleAdImageHandlerTest extends Unit
{
    private ArticleAdImageRepositoryInterface $adImages;
    private AiAdImageSettings $settings;
    private RefineArticleAdImageHandler $handler;

    protected function _before(): void
    {
        $this->adImages = new class implements ArticleAdImageRepositoryInterface {
            /** @var array<string, ArticleAdImage> */
            public array $images = [];

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
                return array_values(array_filter($this->images, static fn (ArticleAdImage $i): bool => $i->articleId === $articleId));
            }

            public function delete(string $id): void
            {
                unset($this->images[$id]);
            }
        };
        $this->adImages->save(new ArticleAdImage(
            id: 'ad-1',
            articleId: 'article-1',
            filePath: 'article-1/ad-1.png',
            format: ArticleAdImageFormat::cases()[0],
            prompt: 'original prompt',
            sourceArticleImageId: null,
            createdAt: new DateTimeImmutable(),
        ));

        $this->settings = new AiAdImageSettings(openaiApiKey: 'sk-test');
        $settings = $this->settings;
        $settingsRepository = new class ($settings) implements AiAdImageSettingsRepositoryInterface {
            public function __construct(private AiAdImageSettings $settings)
            {
            }

            public function get(): AiAdImageSettings
            {
                return $this->settings;
            }

            public function save(AiAdImageSettings $settings): void
            {
                $this->settings = $settings;
            }
        };

        $this->handler = new RefineArticleAdImageHandler(
            $this->adImages,
            new LocalFileStorage(sys_get_temp_dir() . '/refine-ad-image-test-' . bin2hex(random_bytes(4))),
            $settingsRepository,
            new OpenAiImageClient(),
        );
    }

    public function testUnknownAdImageIsRejected(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler->handle(new RefineArticleAdImage('missing', 'Hintergrund heller'));
    }

    public function testInstructionsAreRequired(): void
    {
        $errors = $this->errorsFor(new RefineArticleAdImage('ad-1', '   '));

        $this->assertArrayHasKey('instructions', $errors);
    }

    public function testTooLongInstructionsAreRejected(): void
    {
        $errors = $this->errorsFor(new RefineArticleAdImage('ad-1', str_repeat('a', RefineArticleAdImageHandler::MAX_INSTRUCTIONS_LENGTH + 1)));

        $this->assertArrayHasKey('instructions', $errors);
    }

    public function testCountMustBeInRange(): void
    {
        $this->assertArrayHasKey('count', $this->errorsFor(new RefineArticleAdImage('ad-1', 'Heller', 0)));
        $this->assertArrayHasKey('count', $this->errorsFor(new RefineArticleAdImage('ad-1', 'Heller', 99)));
    }

    public function testRequiresConfiguredApiKey(): void
    {
        $this->settings->openaiApiKey = null;

        $errors = $this->errorsFor(new RefineArticleAdImage('ad-1', 'Heller'));

        $this->assertStringContainsString('API-Key', $errors['instructions']);
    }

    public function testMissingImageFileIsReportedWithoutStoringAnything(): void
    {
        $errors = $this->errorsFor(new RefineArticleAdImage('ad-1', 'Heller'));

        $this->assertStringContainsString('nicht gelesen', $errors['instructions']);
        $this->assertCount(1, $this->adImages->findByArticle('article-1'));
    }

    /** @return array<string, string> */
    private function errorsFor(RefineArticleAdImage $command): array
    {
        try {
            $this->handler->handle($command);
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('Expected a ValidationException.');
    }
}
