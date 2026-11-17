<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleAdImageRepositoryInterface;
use App\Domain\Article\ArticleImage;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;
use RuntimeException;

/**
 * Adds a copy of an AI ad image to the end of the article's product gallery.
 * The file is copied (not moved), so deleting either image later does not
 * affect the other one.
 */
final class AddArticleAdImageToGalleryHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticleAdImageRepositoryInterface $adImages,
        private readonly ArticleRepositoryInterface $articles,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    /** @return string ID of the new gallery image */
    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddArticleAdImageToGallery);

        $adImage = $this->adImages->findById($command->adImageId);
        if ($adImage === null) {
            throw new NotFoundException("Werbebild \"{$command->adImageId}\" wurde nicht gefunden.");
        }

        $article = $this->articles->findById($adImage->articleId);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$adImage->articleId}\" wurde nicht gefunden.");
        }

        $sourcePath = $this->fileStorage->absolutePath('article-ad-images', $adImage->filePath);
        if (!is_file($sourcePath)) {
            throw new ValidationException(['adImage' => 'Die Bilddatei des Werbebilds wurde nicht gefunden.']);
        }

        $imageId = Uuid::v4();
        try {
            $stored = $this->fileStorage->storeLocalFile(
                sourceAbsolutePath: $sourcePath,
                subDirectory: 'articles/' . $article->id,
                storedFilenameWithoutExtension: $imageId,
                originalFilename: basename($adImage->filePath),
                deleteSource: false,
            );
        } catch (RuntimeException $e) {
            throw new ValidationException(['adImage' => 'Das Werbebild konnte nicht in die Galerie übernommen werden: ' . $e->getMessage()]);
        }

        $this->articles->addImage(new ArticleImage(
            id: $imageId,
            articleId: $article->id,
            filePath: $article->id . '/' . $stored['storedFilename'],
            position: count($article->images),
            createdAt: new DateTimeImmutable(),
        ));

        return $imageId;
    }
}
