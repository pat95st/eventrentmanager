<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class SetArticleDocumentImageHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ArticleRepositoryInterface $articles)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof SetArticleDocumentImage);

        $article = $this->articles->findById($command->articleId);
        if ($article === null) {
            throw new NotFoundException("Artikel \"{$command->articleId}\" wurde nicht gefunden.");
        }

        $imageId = $command->imageId !== null && trim($command->imageId) !== '' ? trim($command->imageId) : null;
        if ($imageId !== null) {
            $belongsToArticle = false;
            foreach ($article->images as $image) {
                if ($image->id === $imageId) {
                    $belongsToArticle = true;
                }
            }
            if (!$belongsToArticle) {
                throw new ValidationException(['imageId' => 'Das Bild gehört nicht zu diesem Artikel.']);
            }
        }

        $article->documentImageId = $imageId;
        $article->updatedAt = new DateTimeImmutable();
        $this->articles->save($article);

        return null;
    }
}
