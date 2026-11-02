<?php

declare(strict_types=1);

namespace App\Application\ArticleSet\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\ArticleSet\ArticleSetRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

/**
 * Änderungen wirken nur auf künftige Buchungen: bereits in Veranstaltungen
 * gebuchte Sets behalten ihre eingeplanten Artikel (RentalSetBooking::$allocations).
 */
final class UpdateArticleSetHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ArticleSetRepositoryInterface $sets,
        private readonly ArticleRepositoryInterface $articles,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateArticleSet);

        $set = $this->sets->findById($command->id);
        if ($set === null) {
            throw new NotFoundException("Set \"{$command->id}\" wurde nicht gefunden.");
        }

        $existingUpgradeIds = array_map(static fn ($upgrade) => $upgrade->id, $this->sets->findUpgrades($set->id));
        [$errors, $data] = ValidatesArticleSetInput::validate(
            $set->id, $command->name, $command->price, $command->deploymentDays,
            $command->items, $command->rules, $command->upgrades, $existingUpgradeIds, $this->articles,
        );
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $set->name = trim($command->name);
        $set->description = $command->description !== null && trim($command->description) !== '' ? trim($command->description) : null;
        $set->price = $data['price'];
        $set->deploymentDays = $data['deploymentDays'];
        $set->isActive = $command->isActive;
        $set->updatedAt = new DateTimeImmutable();

        $this->sets->save($set);
        $this->sets->replaceItems($set->id, $data['items']);
        $this->sets->replaceRules($set->id, $data['rules']);
        $this->sets->replaceUpgrades($set->id, $data['upgrades']);

        return null;
    }
}
