<?php

declare(strict_types=1);

namespace App\Application\Receipt\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Receipt\ReceiptStatus;
use DateTimeImmutable;

final class MarkReceiptPaidHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ReceiptRepositoryInterface $receipts)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MarkReceiptPaid);

        $receipt = $this->receipts->findById($command->id);
        if ($receipt === null) {
            throw new NotFoundException("Beleg \"{$command->id}\" wurde nicht gefunden.");
        }

        $now = new DateTimeImmutable();
        $receipt->status = ReceiptStatus::Paid;
        $receipt->paidAt = $now;
        $receipt->updatedAt = $now;
        $this->receipts->save($receipt);

        return null;
    }
}
