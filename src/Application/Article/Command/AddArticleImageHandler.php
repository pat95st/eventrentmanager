<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleImage;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;

final class AddArticleImageHandler implements CommandHandlerInterface
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    private const MAX_SIZE_BYTES = 8 * 1024 * 1024; // 8 MB

    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddArticleImage);

        $article = $this->articles->findById($command->articleId);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$command->articleId}\" wurde nicht gefunden.");
        }

        $file = $command->uploadedFile;

        if (!in_array($file['type'], self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException(['image' => 'Nur JPEG-, PNG-, WebP- oder GIF-Bilder sind erlaubt.']);
        }

        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['image' => 'Das Bild darf höchstens 8 MB groß sein.']);
        }

        $imageId = Uuid::v4();
        $stored = $this->fileStorage->storeUploadedFile($file, 'articles/' . $article->id, $imageId);

        $position = count($article->images);
        $image = new ArticleImage(
            id: $imageId,
            articleId: $article->id,
            filePath: $article->id . '/' . $stored['storedFilename'],
            position: $position,
            createdAt: new DateTimeImmutable(),
        );

        $this->articles->addImage($image);

        return $image->id;
    }
}
