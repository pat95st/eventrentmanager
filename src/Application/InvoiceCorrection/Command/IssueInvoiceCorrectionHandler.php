<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\InvoiceCorrection\InvoiceCorrectionLineSource;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\InvoiceCorrection\InvoiceCorrectionLine;
use App\Domain\InvoiceCorrection\InvoiceCorrectionRepositoryInterface;
use App\Domain\InvoiceCorrection\InvoiceCorrectionStatus;
use App\Domain\InvoiceCorrection\InvoiceCorrectionType;
use App\Domain\Numbering\NumberedDocumentType;
use App\Infrastructure\Persistence\Pdo\DocumentNumberSequence;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;

/**
 * Stellt eine Rechnungskorrektur aus (Migration 121): vergibt die nächste
 * Nummer (RK-0001 …) und das heutige Datum in einer Transaktion.
 *
 * - Stornorechnung: Positionen werden beim Ausstellen neu aus der Rechnung
 *   und den inzwischen ausgestellten Teilkorrekturen gebildet. Eine noch
 *   offene (versendete) Rechnung wird dabei auf "Storniert" gesetzt; eine
 *   bezahlte bleibt "Bezahlt", die Stornorechnung wartet dann auf die
 *   Erstattung.
 * - War die Rechnung bezahlt, wird die Korrektur als "Erstattung/
 *   Nachzahlung offen" markiert (requiresSettlement).
 */
final class IssueInvoiceCorrectionHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly InvoiceCorrectionRepositoryInterface $corrections,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly InvoiceCorrectionLineSource $lineSource,
        private readonly PDO $pdo,
        private readonly DocumentNumberSequence $numberSequence,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof IssueInvoiceCorrection);

        $correction = $this->corrections->findById($command->id);
        if ($correction === null) {
            throw new NotFoundException('Die Rechnungskorrektur wurde nicht gefunden.');
        }
        if (!$correction->isDraft()) {
            throw new ConflictException('Diese Rechnungskorrektur wurde bereits ausgestellt.');
        }
        $invoice = $this->invoices->findById($correction->invoiceId);
        if ($invoice === null) {
            throw new NotFoundException('Die zugehörige Rechnung wurde nicht gefunden.');
        }
        CreateInvoiceCorrectionHandler::assertCorrectable($invoice->status, $this->corrections->findByInvoice($invoice->id), $correction->type, $correction->id);

        if ($correction->type === InvoiceCorrectionType::Cancellation) {
            $lines = [];
            $invoiceDetail = $this->lineSource->detail($invoice->id);
            $correction->taxAmountFixed = $this->lineSource->cancellationTax($invoiceDetail, $correction->id);
            foreach ($this->lineSource->cancellationLines($invoiceDetail, $correction->id) as $sort => $line) {
                $lines[] = new InvoiceCorrectionLine(Uuid::v4(), $correction->id, $sort, $line['description'], $line['quantity'], $line['unitPrice']);
            }
            $correction->lines = $lines;
        }

        $now = new DateTimeImmutable();
        $this->pdo->beginTransaction();
        try {
            $correction->correctionNumber = $this->numberSequence->allocateNext(NumberedDocumentType::InvoiceCorrection);
            $correction->correctionDate = new DateTimeImmutable('today');
            $correction->status = InvoiceCorrectionStatus::Issued;
            $correction->requiresSettlement = $invoice->status === InvoiceStatus::Paid;
            $correction->updatedAt = $now;
            $this->corrections->save($correction);

            if ($correction->type === InvoiceCorrectionType::Cancellation && $invoice->status === InvoiceStatus::Sent) {
                $invoice->status = InvoiceStatus::Cancelled;
                $invoice->updatedAt = $now;
                $this->invoices->save($invoice);
            }
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $correction->correctionNumber;
    }
}
