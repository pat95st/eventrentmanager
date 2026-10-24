<?php

declare(strict_types=1);

namespace App\Application\Category\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class UpdateCategoryHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CategoryRepositoryInterface $categories)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateCategory);

        $category = $this->categories->findById($command->id);
        if ($category === null) {
            throw new NotFoundException("Kategorie \"{$command->id}\" wurde nicht gefunden.");
        }

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen angeben.']);
        }

        if ($command->parentId === $command->id) {
            throw new ValidationException(['parentId' => 'Eine Kategorie kann nicht ihre eigene Elternkategorie sein.']);
        }

        if ($command->parentId !== null) {
            if ($this->categories->findById($command->parentId) === null) {
                throw new ValidationException(['parentId' => 'Die gewählte Elternkategorie existiert nicht.']);
            }
            if (in_array($command->parentId, $this->categories->findDescendantIds($command->id), true)) {
                throw new ValidationException(['parentId' => 'Eine Kategorie kann nicht einer ihrer eigenen Unterkategorien untergeordnet werden.']);
            }
        }

        if ($this->categories->existsByNameAndParent($name, $command->parentId, $command->id)) {
            throw new ValidationException(['name' => 'Diese Kategorie existiert unter derselben Elternkategorie bereits.']);
        }

        $category->name = $name;
        $category->parentId = $command->parentId;
        $category->updatedAt = new DateTimeImmutable();

        $this->categories->save($category);

        return null;
    }
}
