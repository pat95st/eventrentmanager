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
 * Entwurf ODER Versendet -> Storniert. Aus "Bezahlt" ist keine Stornierung
 * mehr möglich (siehe Aufgabenstellung) — dafür gibt es in diesem System
 * bewusst keinen eigenen Workflow (z. B. eine Korrekturrechnung/Gutschrift
 * wäre der fachlich korrekte Weg, liegt aber außerhalb dieses Umfangs).
 * Eine bereits stornierte Rechnung kann nicht erneut storniert werden.
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

        if (!in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Sent], true)) {
            throw new ConflictException('Nur eine Rechnung im Status "Entwurf" oder "Versendet" kann storniert werden.');
        }

        $invoice->status = InvoiceStatus::Cancelled;
        $invoice->updatedAt = new DateTimeImmutable();
        $this->invoices->save($invoice);

        return null;
    }
}
