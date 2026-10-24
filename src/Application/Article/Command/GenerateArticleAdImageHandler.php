<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleAdImage;
use App\Domain\Article\ArticleAdImageFormat;
use App\Domain\Article\ArticleAdImageRepositoryInterface;
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
 * Nutzt bevorzugt ein vorhandenes Artikelfoto als Referenz ("images/edits"),
 * sonst reine Text-zu-Bild-Generierung ("images/generations") — siehe
 * App\Infrastructure\Integration\OpenAi\OpenAiImageClient.
 */
final class GenerateArticleAdImageHandler implements CommandHandlerInterface
{
    private const MAX_ADDITIONAL_INSTRUCTIONS_LENGTH = 500;

    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly CategoryRepositoryInterface $categories,
        private readonly ArticleAdImageRepositoryInterface $adImages,
        private readonly LocalFileStorage $fileStorage,
        private readonly AiAdImageSettingsRepositoryInterface $settingsRepository,
        private readonly OpenAiImageClient $openAiImageClient,
    ) {
    }

    public function handle(CommandInterface $command): string
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

        $additionalInstructions = $this->nullIfEmpty($command->additionalInstructions);
        if ($additionalInstructions !== null && mb_strlen($additionalInstructions) > self::MAX_ADDITIONAL_INSTRUCTIONS_LENGTH) {
            throw new ValidationException(['additionalInstructions' => 'Bitte höchstens ' . self::MAX_ADDITIONAL_INSTRUCTIONS_LENGTH . ' Zeichen angeben.']);
        }

        $settings = $this->settingsRepository->get();
        if (!$settings->isConfigured()) {
            throw new ValidationException(['format' => 'Bitte zuerst einen OpenAI-API-Key unter Einstellungen > KI-Werbebilder hinterlegen.']);
        }

        $sourceArticleImageId = $this->nullIfEmpty($command->sourceArticleImageId);
        $sourceImage = null;
        if ($sourceArticleImageId !== null) {
            foreach ($article->images as $image) {
                if ($image->id === $sourceArticleImageId) {
                    $sourceImage = $image;
                }
            }
            if ($sourceImage === null) {
                throw new ValidationException(['sourceArticleImageId' => 'Das ausgewählte Referenzfoto wurde nicht gefunden.']);
            }
        }

        $manufacturer = $this->manufacturers->findById($article->manufacturerId);
        $category = $article->categoryId !== null ? $this->categories->findById($article->categoryId) : null;

        try {
            if ($sourceImage !== null) {
                $prompt = $this->buildEditPrompt($article, $manufacturer?->name, $category?->name, $additionalInstructions);

                $absolutePath = $this->fileStorage->absolutePath('articles', $sourceImage->filePath);
                $referenceBytes = file_get_contents($absolutePath);
                if ($referenceBytes === false) {
                    throw new ValidationException(['sourceArticleImageId' => 'Das Referenzfoto konnte nicht gelesen werden.']);
                }
                $mimeType = function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: 'image/jpeg') : 'image/jpeg';

                $base64 = $this->openAiImageClient->generateFromImage(
                    apiKey: $settings->openaiApiKey,
                    prompt: $prompt,
                    size: $format->openAiSize(),
                    referenceImageBytes: $referenceBytes,
                    referenceImageMimeType: $mimeType,
                    referenceImageFilename: basename($sourceImage->filePath),
                );
            } else {
                $prompt = $this->buildGenerationPrompt($article, $manufacturer?->name, $category?->name, $additionalInstructions);

                $base64 = $this->openAiImageClient->generateFromText(
                    apiKey: $settings->openaiApiKey,
                    prompt: $prompt,
                    size: $format->openAiSize(),
                );
            }
        } catch (OpenAiImageException $e) {
            throw new ValidationException(['format' => 'Werbebild konnte nicht generiert werden: ' . $e->getMessage()]);
        }

        $imageBytes = base64_decode($base64, true);
        if ($imageBytes === false) {
            throw new ValidationException(['format' => 'OpenAI hat ungültige Bilddaten geliefert.']);
        }

        $imageId = Uuid::v4();
        $stored = $this->fileStorage->storeRawBytes(
            bytes: $imageBytes,
            subDirectory: 'article-ad-images/' . $article->id,
            storedFilenameWithoutExtension: $imageId,
            originalFilename: $imageId . '.png',
            mimeType: 'image/png',
        );

        $adImage = new ArticleAdImage(
            id: $imageId,
            articleId: $article->id,
            filePath: $article->id . '/' . $stored['storedFilename'],
            format: $format,
            prompt: $prompt,
            sourceArticleImageId: $sourceImage?->id,
            createdAt: new DateTimeImmutable(),
        );

        $this->adImages->save($adImage);

        return $adImage->id;
    }

    private function buildGenerationPrompt(\App\Domain\Article\Article $article, ?string $manufacturerName, ?string $categoryName, ?string $additionalInstructions): string
    {
        $details = $this->productDetailsText($article, $manufacturerName, $categoryName);

        $prompt = 'Erstelle ein professionelles, hochwertiges Werbebild für Social Media (z. B. Instagram) für folgendes '
            . 'Produkt aus einem Veranstaltungstechnik-Verleih: ' . $details . ' '
            . 'Stil: realistische, moderne Produktfotografie mit ansprechender Beleuchtung und einem leicht abgedunkelten, '
            . 'stimmungsvollen Bühnen- oder Veranstaltungshintergrund (z. B. Lichtstrahlen, Bokeh), das Produkt steht klar im Fokus. '
            . 'Keine Logos, kein Text und keine Wasserzeichen im Bild.';

        if ($additionalInstructions !== null) {
            $prompt .= ' Zusätzliche Anweisungen: ' . $additionalInstructions;
        }

        return $prompt;
    }

    private function buildEditPrompt(\App\Domain\Article\Article $article, ?string $manufacturerName, ?string $categoryName, ?string $additionalInstructions): string
    {
        $details = $this->productDetailsText($article, $manufacturerName, $categoryName);

        $prompt = 'Style dieses Produktfoto zu einem professionellen Werbebild für Social Media (z. B. Instagram) um. '
            . 'Es zeigt folgendes Produkt aus einem Veranstaltungstechnik-Verleih: ' . $details . ' '
            . 'Behalte das Produkt und sein Erscheinungsbild exakt bei, verändere nur Hintergrund, Beleuchtung und Stimmung: '
            . 'ein moderner, stimmungsvoller Bühnen- oder Veranstaltungshintergrund (z. B. Lichtstrahlen, Bokeh). '
            . 'Keine Logos, kein Text und keine Wasserzeichen im Bild.';

        if ($additionalInstructions !== null) {
            $prompt .= ' Zusätzliche Anweisungen: ' . $additionalInstructions;
        }

        return $prompt;
    }

    private function productDetailsText(\App\Domain\Article\Article $article, ?string $manufacturerName, ?string $categoryName): string
    {
        $parts = [$article->name];
        if ($manufacturerName !== null) {
            $parts[] = 'Hersteller: ' . $manufacturerName;
        }
        if ($categoryName !== null) {
            $parts[] = 'Kategorie: ' . $categoryName;
        }
        if ($article->description !== null && trim($article->description) !== '') {
            $parts[] = 'Beschreibung: ' . trim($article->description);
        }

        return implode('. ', $parts) . '.';
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
