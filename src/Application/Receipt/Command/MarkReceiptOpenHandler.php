<?php

declare(strict_types=1);

namespace App\Application\Receipt\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Receipt\ReceiptStatus;
use DateTimeImmutable;

final class MarkReceiptOpenHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ReceiptRepositoryInterface $receipts)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MarkReceiptOpen);

        $receipt = $this->receipts->findById($command->id);
        if ($receipt === null) {
            throw new NotFoundException("Beleg \"{$command->id}\" wurde nicht gefunden.");
        }

        $receipt->status = ReceiptStatus::Open;
        $receipt->paidAt = null;
        $receipt->updatedAt = new DateTimeImmutable();
        $this->receipts->save($receipt);

        return null;
    }
}
