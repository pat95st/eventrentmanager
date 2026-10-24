<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\OrderConfirmation\OrderConfirmationRepositoryInterface;

final class DeleteOrderConfirmationHandler implements CommandHandlerInterface
{
    public function __construct(private readonly OrderConfirmationRepositoryInterface $orderConfirmations)
    {
    }

    public function handle(CommandInterface $command): void
    {
        assert($command instanceof DeleteOrderConfirmation);

        $orderConfirmation = $this->orderConfirmations->findById($command->id);
        if ($orderConfirmation === null) {
            throw new NotFoundException("Auftragsbestätigung \"{$command->id}\" wurde nicht gefunden.");
        }

        if (!$orderConfirmation->isEditable()) {
            throw new ConflictException('Nur eine Auftragsbestätigung im Status "Entwurf" kann gelöscht werden.');
        }

        $this->orderConfirmations->delete($command->id);
    }
}
