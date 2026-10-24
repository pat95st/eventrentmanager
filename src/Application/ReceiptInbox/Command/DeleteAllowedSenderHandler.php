<?php

declare(strict_types=1);

namespace App\Application\ReceiptInbox\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\ReceiptInbox\ReceiptInboxAllowedSenderRepositoryInterface;

final class DeleteAllowedSenderHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ReceiptInboxAllowedSenderRepositoryInterface $senders)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteAllowedSender);

        if ($this->senders->findById($command->id) === null) {
            throw new NotFoundException("Absenderadresse \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->senders->delete($command->id);

        return null;
    }
}
