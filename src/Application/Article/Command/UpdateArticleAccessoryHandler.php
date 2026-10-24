<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleAccessoryRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class UpdateArticleAccessoryHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ArticleAccessoryRepositoryInterface $accessories)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateArticleAccessory);

        $accessory = $this->accessories->findById($command->id);
        if ($accessory === null) {
            throw new NotFoundException("Zubehör \"{$command->id}\" wurde nicht gefunden.");
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

        $accessory->quantity = $quantity;
        $accessory->description = $description;
        $accessory->updatedAt = new DateTimeImmutable();

        $this->accessories->save($accessory);

        return null;
    }
}
