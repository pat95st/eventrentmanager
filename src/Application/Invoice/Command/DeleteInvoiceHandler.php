<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;

/** Löscht eine Rechnung — nur solange sie noch Entwurf ist (analog DeleteOfferHandler), damit einer Rechnung mit bereits vergebener Nummer niemals rückstandslos verschwinden kann. */
final class DeleteInvoiceHandler implements CommandHandlerInterface
{
    public function __construct(private readonly InvoiceRepositoryInterface $invoices)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteInvoice);

        $invoice = $this->invoices->findById($command->id);
        if ($invoice === null) {
            throw new NotFoundException("Rechnung \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new ConflictException('Nur eine Rechnung im Status "Entwurf" kann gelöscht werden.');
        }

        $this->invoices->delete($invoice->id);

        return null;
    }
}
