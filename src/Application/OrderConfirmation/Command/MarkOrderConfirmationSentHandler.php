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

final class MarkOrderConfirmationSentHandler implements CommandHandlerInterface
{
    public function __construct(private readonly OrderConfirmationRepositoryInterface $orderConfirmations)
    {
    }

    public function handle(CommandInterface $command): void
    {
        assert($command instanceof MarkOrderConfirmationSent);

        $orderConfirmation = $this->orderConfirmations->findById($command->id);
        if ($orderConfirmation === null) {
            throw new NotFoundException("Auftragsbestätigung \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($orderConfirmation->status !== OrderConfirmationStatus::Draft) {
            throw new ConflictException('Nur eine Auftragsbestätigung im Status "Entwurf" kann als versendet markiert werden.');
        }

        $orderConfirmation->status = OrderConfirmationStatus::Sent;
        $orderConfirmation->updatedAt = new DateTimeImmutable();

        $this->orderConfirmations->save($orderConfirmation);
    }
}
