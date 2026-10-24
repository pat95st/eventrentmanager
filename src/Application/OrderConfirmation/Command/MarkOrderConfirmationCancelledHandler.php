<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\OrderConfirmation\OrderConfirmationRepositoryInterface;
use App\Domain\OrderConfirmation\OrderConfirmationStatus;
use DateTimeImmutable;

final class MarkOrderConfirmationCancelledHandler implements CommandHandlerInterface
{
    public function __construct(private readonly OrderConfirmationRepositoryInterface $orderConfirmations)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MarkOrderConfirmationCancelled);

        $orderConfirmation = $this->orderConfirmations->findById($command->id);
        if ($orderConfirmation === null) {
            throw new NotFoundException("Auftragsbestätigung \"{$command->id}\" wurde nicht gefunden.");
        }

        if (!in_array($orderConfirmation->status, [OrderConfirmationStatus::Draft, OrderConfirmationStatus::Sent], true)) {
            throw new ConflictException('Nur eine Auftragsbestätigung im Status "Entwurf" oder "Versendet" kann storniert werden.');
        }

        $orderConfirmation->status = OrderConfirmationStatus::Cancelled;
        $orderConfirmation->updatedAt = new DateTimeImmutable();

        $this->orderConfirmations->save($orderConfirmation);

        return null;
    }
}
