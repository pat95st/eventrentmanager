<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleAdImageRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteArticleAdImageHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticleAdImageRepositoryInterface $adImages,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteArticleAdImage);

        $image = $this->adImages->findById($command->id);
        if ($image === null) {
            throw new NotFoundException("Werbebild \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->adImages->delete($command->id);
        $this->fileStorage->delete('article-ad-images', $image->filePath);

        return null;
    }
}
