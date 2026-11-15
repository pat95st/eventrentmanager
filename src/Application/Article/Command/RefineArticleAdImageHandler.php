<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleAdImage;
use App\Domain\Article\ArticleAdImageRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\AiAdImageSettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Integration\OpenAi\OpenAiImageClient;
use App\Infrastructure\Integration\OpenAi\OpenAiImageException;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Refines an existing AI ad image that does not fit: the image itself is used
 * as the reference for OpenAI's "images/edits" endpoint together with the
 * user's change requests. The result is stored as a NEW ad image in the same
 * format (the original stays in the history, see ArticleAdImage) and remembers
 * the image it was derived from (sourceAdImageId).
 */
final class RefineArticleAdImageHandler implements CommandHandlerInterface
{
    public const MAX_INSTRUCTIONS_LENGTH = 1000;

    public function __construct(
        private readonly ArticleAdImageRepositoryInterface $adImages,
        private readonly LocalFileStorage $fileStorage,
        private readonly AiAdImageSettingsRepositoryInterface $settingsRepository,
        private readonly OpenAiImageClient $openAiImageClient,
    ) {
    }

    /** @return list<string> IDs of the newly created ad images */
    public function handle(CommandInterface $command): array
    {
        assert($command instanceof RefineArticleAdImage);

        $source = $this->adImages->findById($command->adImageId);
        if ($source === null) {
            throw new NotFoundException("Werbebild \"{$command->adImageId}\" wurde nicht gefunden.");
        }

        $instructions = trim($command->instructions);
        if ($instructions === '') {
            throw new ValidationException(['instructions' => 'Bitte beschreiben, was am Werbebild geändert werden soll.']);
        }
        if (mb_strlen($instructions) > self::MAX_INSTRUCTIONS_LENGTH) {
            throw new ValidationException(['instructions' => 'Bitte höchstens ' . self::MAX_INSTRUCTIONS_LENGTH . ' Zeichen angeben.']);
        }

        if ($command->count < 1 || $command->count > GenerateArticleAdImageHandler::MAX_COUNT) {
            throw new ValidationException(['count' => 'Bitte zwischen 1 und ' . GenerateArticleAdImageHandler::MAX_COUNT . ' Varianten auswählen.']);
        }

        $settings = $this->settingsRepository->get();
        if (!$settings->isConfigured()) {
            throw new ValidationException(['instructions' => 'Bitte zuerst einen OpenAI-API-Key unter Einstellungen > KI-Werbebilder hinterlegen.']);
        }

        $absolutePath = $this->fileStorage->absolutePath('article-ad-images', $source->filePath);
        $referenceBytes = is_file($absolutePath) ? file_get_contents($absolutePath) : false;
        if ($referenceBytes === false) {
            throw new ValidationException(['instructions' => 'Die Bilddatei des Werbebilds konnte nicht gelesen werden.']);
        }
        $mimeType = function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: 'image/png') : 'image/png';

        $prompt = $this->buildPrompt($instructions);

        try {
            $base64Images = $this->openAiImageClient->generateFromImage(
                apiKey: $settings->openaiApiKey,
                model: $settings->effectiveImageModel(),
                prompt: $prompt,
                size: $source->format->openAiSize(),
                referenceImageBytes: $referenceBytes,
                referenceImageMimeType: $mimeType,
                referenceImageFilename: basename($source->filePath),
                count: $command->count,
            );
        } catch (OpenAiImageException $e) {
            throw new ValidationException(['instructions' => 'Werbebild konnte nicht überarbeitet werden: ' . $e->getMessage()]);
        }

        // Decode everything first so an invalid response stores nothing.
        $decodedImages = [];
        foreach ($base64Images as $base64) {
            $imageBytes = base64_decode($base64, true);
            if ($imageBytes === false) {
                throw new ValidationException(['instructions' => 'OpenAI hat ungültige Bilddaten geliefert.']);
            }
            $decodedImages[] = $imageBytes;
        }

        $ids = [];
        foreach ($decodedImages as $imageBytes) {
            $imageId = Uuid::v4();
            $stored = $this->fileStorage->storeRawBytes(
                bytes: $imageBytes,
                subDirectory: 'article-ad-images/' . $source->articleId,
                storedFilenameWithoutExtension: $imageId,
                originalFilename: $imageId . '.png',
                mimeType: 'image/png',
            );

            $this->adImages->save(new ArticleAdImage(
                id: $imageId,
                articleId: $source->articleId,
                filePath: $source->articleId . '/' . $stored['storedFilename'],
                format: $source->format,
                prompt: $prompt,
                sourceArticleImageId: $source->sourceArticleImageId,
                createdAt: new DateTimeImmutable(),
                sourceAdImageId: $source->id,
            ));
            $ids[] = $imageId;
        }

        return $ids;
    }

    private function buildPrompt(string $instructions): string
    {
        return 'Überarbeite dieses Werbebild für Social Media (z. B. Instagram) eines Veranstaltungstechnik-Verleihs. '
            . 'Gewünschte Änderungen: ' . $instructions . ' '
            . 'Setze ausschließlich diese Änderungen um und behalte alles andere — insbesondere das Produkt, sein '
            . 'Erscheinungsbild, den Bildaufbau und den Stil — möglichst unverändert bei. '
            . 'Füge keine Logos, Texte oder Wasserzeichen hinzu, sofern die Änderungswünsche das nicht ausdrücklich verlangen.';
    }
}
