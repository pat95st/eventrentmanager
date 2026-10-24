<?php

declare(strict_types=1);

namespace App\Application\Category\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Category\Category;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateCategoryHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CategoryRepositoryInterface $categories)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateCategory);

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen angeben.']);
        }

        if ($command->parentId !== null && $this->categories->findById($command->parentId) === null) {
            throw new ValidationException(['parentId' => 'Die gewählte Elternkategorie existiert nicht.']);
        }

        if ($this->categories->existsByNameAndParent($name, $command->parentId)) {
            throw new ValidationException(['name' => 'Diese Kategorie existiert unter derselben Elternkategorie bereits.']);
        }

        $now = new DateTimeImmutable();
        $category = new Category(
            id: Uuid::v4(),
            name: $name,
            parentId: $command->parentId,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->categories->save($category);

        return $category->id;
    }
}
