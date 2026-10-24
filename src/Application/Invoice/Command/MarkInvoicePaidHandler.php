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

/** Versendet -> Bezahlt. Muss zuerst als versendet markiert worden sein (kein Sprung aus dem Entwurf, keine bereits bezahlte/stornierte Rechnung erneut). */
final class MarkInvoicePaidHandler implements CommandHandlerInterface
{
    public function __construct(private readonly InvoiceRepositoryInterface $invoices)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MarkInvoicePaid);

        $invoice = $this->invoices->findById($command->id);
        if ($invoice === null) {
            throw new NotFoundException("Rechnung \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($invoice->status !== InvoiceStatus::Sent) {
            throw new ConflictException('Nur eine versendete Rechnung kann als bezahlt markiert werden.');
        }

        $now = new DateTimeImmutable();
        $invoice->status = InvoiceStatus::Paid;
        $invoice->paidAt = $now;
        $invoice->updatedAt = $now;
        $this->invoices->save($invoice);

        return null;
    }
}
