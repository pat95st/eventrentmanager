<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteArticleImageHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticleRepositoryInterface $articles,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteArticleImage);

        $image = $this->articles->deleteImage($command->imageId);
        if ($image === null) {
            throw new NotFoundException("Bild \"{$command->imageId}\" wurde nicht gefunden.");
        }

        $this->fileStorage->delete('articles', $image->filePath);

        // War es das Dokumentbild (Migration 109), Auswahl zurücksetzen.
        $article = $this->articles->findById($image->articleId);
        if ($article !== null && $article->documentImageId === $image->id) {
            $article->documentImageId = null;
            $this->articles->save($article);
        }

        return null;
    }
}
