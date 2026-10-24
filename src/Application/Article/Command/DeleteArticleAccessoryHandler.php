<?php

declare(strict_types=1);

namespace App\Application\Article\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleAccessoryRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class DeleteArticleAccessoryHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ArticleAccessoryRepositoryInterface $accessories)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteArticleAccessory);

        $accessory = $this->accessories->findById($command->id);
        if ($accessory === null) {
            throw new NotFoundException("Zubehör \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->accessories->delete($command->id);

        return null;
    }
}
