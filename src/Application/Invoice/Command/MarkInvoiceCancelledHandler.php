<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use DateTimeImmutable;

/**
 * Entwurf -> Storniert. Versendete und bezahlte Rechnungen werden über eine
 * Stornorechnung storniert (Rechnungskorrekturen, Migration 121, siehe
 * IssueInvoiceCorrectionHandler), damit das Storno als eigenes Dokument mit
 * Nummer vorliegt. Eine bereits stornierte Rechnung kann nicht erneut
 * storniert werden.
 */
final class MarkInvoiceCancelledHandler implements CommandHandlerInterface
{
    public function __construct(private readonly InvoiceRepositoryInterface $invoices)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MarkInvoiceCancelled);

        $invoice = $this->invoices->findById($command->id);
        if ($invoice === null) {
            throw new NotFoundException("Rechnung \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($invoice->status === InvoiceStatus::Sent || $invoice->status === InvoiceStatus::Paid) {
            throw new ConflictException('Versendete oder bezahlte Rechnungen bitte über „Stornorechnung erstellen“ stornieren.');
        }
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new ConflictException('Nur eine Rechnung im Status "Entwurf" kann direkt storniert werden.');
        }

        $invoice->status = InvoiceStatus::Cancelled;
        $invoice->updatedAt = new DateTimeImmutable();
        $this->invoices->save($invoice);

        return null;
    }
}
