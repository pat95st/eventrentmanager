<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;
use App\Infrastructure\Persistence\Pdo\InvoiceNumberSequence;
use DateTimeImmutable;
use PDO;

/**
 * Entwurf -> Versendet. Der einzige Moment, in dem eine Rechnung eine
 * gesetzlich verbindliche Rechnungsnummer erhält (siehe InvoiceNumberSequence
 * für die Race-Condition-sichere Vergabe): invoiceNumber wird vergeben,
 * invoiceDate auf heute gesetzt, dueDate = heute + InvoiceSettings::
 * $paymentTermDays, und der aktuelle Stand von InvoiceSettings::
 * $isSmallBusiness/$vatRatePercent wird auf die Rechnung SELBST
 * gesnapshotet (siehe Invoice-Klassen-Doc), damit eine spätere Änderung der
 * firmenweiten Steuereinstellungen diese Rechnung nie rückwirkend verändert.
 *
 * Verlangt zwingend einen zugeordneten Kunden und mindestens eine Position
 * (ValidationException sonst) — eine Rechnung ohne Kunde oder Positionen
 * darf nie eine echte Nummer erhalten.
 *
 * Nummernvergabe UND Speichern der Rechnung laufen in derselben
 * Transaktion: schlägt das Speichern fehl, wird auch die Nummernvergabe
 * zurückgerollt (siehe InvoiceNumberSequence-Klassen-Doc).
 */
final class MarkInvoiceSentHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
        private readonly InvoiceNumberSequence $numberSequence,
        private readonly PDO $pdo,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MarkInvoiceSent);

        $invoice = $this->invoices->findById($command->id);
        if ($invoice === null) {
            throw new NotFoundException("Rechnung \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new ConflictException('Nur eine Rechnung im Status "Entwurf" kann als versendet markiert werden.');
        }

        if ($invoice->customerId === null) {
            throw new ValidationException(['customerId' => 'Für das Versenden muss der Rechnung zuerst ein Kunde zugeordnet werden.']);
        }

        $items = $this->invoices->findItemsByInvoice($invoice->id);
        if ($items === []) {
            throw new ValidationException(['items' => 'Eine Rechnung ohne Positionen kann nicht versendet werden.']);
        }

        $settings = $this->invoiceSettings->get();
        $now = new DateTimeImmutable();

        $this->pdo->beginTransaction();

        try {
            $invoiceNumber = $this->numberSequence->allocateNext();

            $invoice->invoiceNumber = $invoiceNumber;
            $invoice->invoiceDate = $now;
            $invoice->dueDate = $now->modify('+' . $settings->paymentTermDays . ' days');
            $invoice->isSmallBusiness = $settings->isSmallBusiness;
            $invoice->vatRatePercent = $settings->vatRatePercent;
            $invoice->status = InvoiceStatus::Sent;
            $invoice->updatedAt = $now;

            $this->invoices->save($invoice);

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        return $invoice->invoiceNumber;
    }
}
