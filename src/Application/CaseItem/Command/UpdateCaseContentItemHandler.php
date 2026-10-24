<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CaseContent\CaseContentItemRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class UpdateCaseContentItemHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CaseContentItemRepositoryInterface $contentItems)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateCaseContentItem);

        $item = $this->contentItems->findById($command->id);
        if ($item === null) {
            throw new NotFoundException("Packlisten-Eintrag \"{$command->id}\" wurde nicht gefunden.");
        }

        $quantity = filter_var($command->quantity, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1) {
            throw new ValidationException(['quantity' => 'Bitte eine Menge von mindestens 1 angeben.']);
        }

        $item->quantity = $quantity;
        $item->updatedAt = new DateTimeImmutable();

        $this->contentItems->save($item);

        return null;
    }
}
