<?php

declare(strict_types=1);

namespace App\Application\ArticleSet\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\ArticleSet\ArticleSet;
use App\Domain\ArticleSet\ArticleSetRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateArticleSetHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticleSetRepositoryInterface $sets,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateArticleSet);

        $id = Uuid::v4();
        [$errors, $data] = ValidatesArticleSetInput::validate(
            $id, $command->name, $command->price, $command->deploymentDays,
            $command->items, $command->rules, $command->upgrades, [], $this->articles,
        );
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $description = $command->description !== null && trim($command->description) !== '' ? trim($command->description) : null;
        $this->sets->save(new ArticleSet($id, trim($command->name), $description, $data['price'], $data['deploymentDays'], $command->isActive, $now, $now));
        $this->sets->replaceItems($id, $data['items']);
        $this->sets->replaceRules($id, $data['rules']);
        $this->sets->replaceUpgrades($id, $data['upgrades']);

        return $id;
    }
}
