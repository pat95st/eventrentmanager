<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleAccessory;
use App\Domain\Article\ArticleAccessoryRepositoryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

final class AddArticleAccessoryHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticleAccessoryRepositoryInterface $accessories,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddArticleAccessory);

        if ($this->articles->findById($command->articleId) === null) {
            throw new NotFoundException("Artikel \"{$command->articleId}\" wurde nicht gefunden.");
        }

        $errors = [];

        $quantity = filter_var($command->quantity, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1) {
            $errors['quantity'] = 'Bitte eine Anzahl von mindestens 1 angeben.';
        }

        $description = trim($command->description);
        if ($description === '') {
            $errors['description'] = 'Bitte eine Bezeichnung angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $accessory = new ArticleAccessory(
            id: Uuid::v4(),
            articleId: $command->articleId,
            quantity: $quantity,
            description: $description,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->accessories->save($accessory);

        return $accessory->id;
    }
}
