<?php

declare(strict_types=1);

namespace App\Application\Category\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;

final class DeleteCategoryHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCategory);

        $category = $this->categories->findById($command->id);
        if ($category === null) {
            throw new NotFoundException("Kategorie \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($this->categories->findChildren($command->id) !== []) {
            throw new ConflictException('Diese Kategorie enthält noch Unterkategorien. Bitte zuerst verschieben oder löschen.');
        }

        if ($this->articles->countByCategory($command->id) > 0) {
            throw new ConflictException('Dieser Kategorie sind noch Artikel zugeordnet. Bitte zuerst umhängen oder entfernen.');
        }

        $this->categories->delete($command->id);

        return null;
    }
}
