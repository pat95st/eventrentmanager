<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\Article;
use App\Domain\Article\ArticleAdImage;
use App\Domain\Article\ArticleAdImageFormat;
use App\Domain\Article\ArticleAdImageRepositoryInterface;
use App\Domain\Article\ArticleImage;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Settings\AiAdImageSettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Integration\OpenAi\OpenAiImageClient;
use App\Infrastructure\Integration\OpenAi\OpenAiImageException;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Generiert ein KI-Werbebild für einen Artikel (siehe ArticleAdImage) über
 * OpenAIs gpt-image-1 (Anthropic/Claude bietet keine Bildgenerierung an).
 * Nutzt auf Wunsch vorhandene Galeriebilder des Artikels (alle oder ein
 * ausgewähltes) als Referenz ("images/edits"), damit das Produkt korrekt
 * dargestellt wird, sonst reine Text-zu-Bild-Generierung
 * ("images/generations") — siehe
 * App\Infrastructure\Integration\OpenAi\OpenAiImageClient.
 */
final class GenerateArticleAdImageHandler implements CommandHandlerInterface
{
    private const MAX_ADDITIONAL_INSTRUCTIONS_LENGTH = 500;
    public const MAX_COUNT = 4;
    // Upper bound of gallery photos sent as references (keeps the upload small).
    public const MAX_REFERENCE_IMAGES = 4;
    // Formats accepted by the images/edits endpoint.
    private const REFERENCE_MIME_TYPES = ['image/png', 'image/jpeg', 'image/webp'];

    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly CategoryRepositoryInterface $categories,
        private readonly ArticleAdImageRepositoryInterface $adImages,
        private readonly LocalFileStorage $fileStorage,
        private readonly AiAdImageSettingsRepositoryInterface $settingsRepository,
        private readonly OpenAiImageClient $openAiImageClient,
        private readonly ArticleAdImagePromptBuilder $promptBuilder = new ArticleAdImagePromptBuilder(),
    ) {
    }

    /** @return list<string> IDs der erzeugten Werbebilder */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof GenerateArticleAdImage);

        $article = $this->articles->findById($command->articleId);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$command->articleId}\" wurde nicht gefunden.");
        }

        $format = ArticleAdImageFormat::tryFrom($command->format);
        if ($format === null) {
            throw new ValidationException(['format' => 'Bitte ein gültiges Format auswählen.']);
        }

        if ($command->count < 1 || $command->count > self::MAX_COUNT) {
            throw new ValidationException(['count' => 'Bitte zwischen 1 und ' . self::MAX_COUNT . ' Werbebilder auswählen.']);
        }

        $additionalInstructions = $this->nullIfEmpty($command->additionalInstructions);
        if ($additionalInstructions !== null && mb_strlen($additionalInstructions) > self::MAX_ADDITIONAL_INSTRUCTIONS_LENGTH) {
            throw new ValidationException(['additionalInstructions' => 'Bitte höchstens ' . self::MAX_ADDITIONAL_INSTRUCTIONS_LENGTH . ' Zeichen angeben.']);
        }

        $settings = $this->settingsRepository->get();
        if (!$settings->isConfigured()) {
            throw new ValidationException(['format' => 'Bitte zuerst einen OpenAI-API-Key unter Einstellungen > KI-Werbebilder hinterlegen.']);
        }

        $referenceImages = $this->referenceImages($article, $this->nullIfEmpty($command->sourceArticleImageId), $command->useGalleryImages);

        $manufacturer = $this->manufacturers->findById(($article->manufacturerId ?? ''));
        $category = $article->categoryId !== null ? $this->categories->findById($article->categoryId) : null;

        $prompt = $this->promptBuilder->build(
            $article,
            $manufacturer,
            $category,
            $referenceImages !== [],
            $command->includeArticleName,
            $command->includeProperties,
            $additionalInstructions,
            $command->includePrice,
            count($referenceImages),
        );

        try {
            if ($referenceImages !== []) {
                $base64Images = $this->openAiImageClient->generateFromImages(
                    apiKey: $settings->openaiApiKey,
                    model: $settings->effectiveImageModel(),
                    prompt: $prompt,
                    size: $format->openAiSize(),
                    referenceImages: array_map(static fn (array $r): array => [
                        'bytes' => $r['bytes'],
                        'mimeType' => $r['mimeType'],
                        'filename' => $r['filename'],
                    ], $referenceImages),
                    count: $command->count,
                );
            } else {
                $base64Images = $this->openAiImageClient->generateFromText(
                    apiKey: $settings->openaiApiKey,
                    model: $settings->effectiveImageModel(),
                    prompt: $prompt,
                    size: $format->openAiSize(),
                    count: $command->count,
                );
            }
        } catch (OpenAiImageException $e) {
            throw new ValidationException(['format' => 'Werbebild konnte nicht generiert werden: ' . $e->getMessage()]);
        }

        // Decode everything first so an invalid response stores nothing.
        $decodedImages = [];
        foreach ($base64Images as $base64) {
            $imageBytes = base64_decode($base64, true);
            if ($imageBytes === false) {
                throw new ValidationException(['format' => 'OpenAI hat ungültige Bilddaten geliefert.']);
            }
            $decodedImages[] = $imageBytes;
        }

        $ids = [];
        foreach ($decodedImages as $imageBytes) {
            $imageId = Uuid::v4();
            $stored = $this->fileStorage->storeRawBytes(
                bytes: $imageBytes,
                subDirectory: 'article-ad-images/' . $article->id,
                storedFilenameWithoutExtension: $imageId,
                originalFilename: $imageId . '.png',
                mimeType: 'image/png',
            );

            $this->adImages->save(new ArticleAdImage(
                id: $imageId,
                articleId: $article->id,
                filePath: $article->id . '/' . $stored['storedFilename'],
                format: $format,
                prompt: $prompt,
                sourceArticleImageId: $referenceImages !== [] ? $referenceImages[0]['id'] : null,
                createdAt: new DateTimeImmutable(),
            ));
            $ids[] = $imageId;
        }

        return $ids;
    }

    /**
     * Loads the gallery photos used as references: the explicitly selected
     * photo, or (with $useGalleryImages) the first MAX_REFERENCE_IMAGES photos
     * in a supported format.
     *
     * @return list<array{id: string, bytes: string, mimeType: string, filename: string}>
     */
    private function referenceImages(Article $article, ?string $sourceArticleImageId, bool $useGalleryImages): array
    {
        if ($sourceArticleImageId !== null) {
            foreach ($article->images as $image) {
                if ($image->id === $sourceArticleImageId) {
                    $reference = $this->loadReference($image);
                    if ($reference === null) {
                        throw new ValidationException(['sourceArticleImageId' => 'Das Referenzfoto konnte nicht gelesen werden.']);
                    }

                    return [$reference];
                }
            }
            throw new ValidationException(['sourceArticleImageId' => 'Das ausgewählte Referenzfoto wurde nicht gefunden.']);
        }

        if (!$useGalleryImages) {
            return [];
        }

        $images = $article->images;
        usort($images, static fn (ArticleImage $a, ArticleImage $b): int => $a->position <=> $b->position);

        $references = [];
        foreach ($images as $image) {
            if (count($references) >= self::MAX_REFERENCE_IMAGES) {
                break;
            }
            $reference = $this->loadReference($image);
            if ($reference !== null && in_array($reference['mimeType'], self::REFERENCE_MIME_TYPES, true)) {
                $references[] = $reference;
            }
        }

        if ($references === []) {
            throw new ValidationException(['useGalleryImages' => 'Der Artikel hat keine Galeriebilder (PNG, JPEG oder WebP), die als Vorlage dienen können.']);
        }

        return $references;
    }

    /** @return array{id: string, bytes: string, mimeType: string, filename: string}|null */
    private function loadReference(ArticleImage $image): ?array
    {
        $absolutePath = $this->fileStorage->absolutePath('articles', $image->filePath);
        $bytes = is_file($absolutePath) ? file_get_contents($absolutePath) : false;
        if ($bytes === false) {
            return null;
        }
        $mimeType = function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: 'image/jpeg') : 'image/jpeg';

        return ['id' => $image->id, 'bytes' => $bytes, 'mimeType' => $mimeType, 'filename' => basename($image->filePath)];
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
