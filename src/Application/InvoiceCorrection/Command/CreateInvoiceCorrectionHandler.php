<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\InvoiceCorrection\InvoiceCorrectionLineSource;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\InvoiceCorrection\InvoiceCorrection;
use App\Domain\InvoiceCorrection\InvoiceCorrectionLine;
use App\Domain\InvoiceCorrection\InvoiceCorrectionRepositoryInterface;
use App\Domain\InvoiceCorrection\InvoiceCorrectionStatus;
use App\Domain\InvoiceCorrection\InvoiceCorrectionType;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Legt eine Stornorechnung oder Rechnungskorrektur als Entwurf an
 * (Migration 121). Nur für versendete oder bezahlte Rechnungen; je Rechnung
 * höchstens eine Stornorechnung, danach keine weiteren Korrekturen.
 * Stornorechnungen übernehmen alle Positionen negativ (siehe
 * InvoiceCorrectionLineSource), Teilkorrekturen die eingegebenen Positionen.
 */
final class CreateInvoiceCorrectionHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly InvoiceCorrectionRepositoryInterface $corrections,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly InvoiceCorrectionLineSource $lineSource,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateInvoiceCorrection);

        $invoice = $this->invoices->findById($command->invoiceId);
        if ($invoice === null) {
            throw new NotFoundException("Rechnung \"{$command->invoiceId}\" wurde nicht gefunden.");
        }
        self::assertCorrectable($invoice->status, $this->corrections->findByInvoice($invoice->id), $command->type);

        $id = Uuid::v4();
        $errors = [];
        $reason = trim($command->input->reason);
        if ($reason === '') {
            $errors['reason'] = 'Bitte den Grund der Korrektur angeben (erscheint auf dem Dokument).';
        }

        $taxFixed = null;
        if ($command->type === InvoiceCorrectionType::Cancellation) {
            $lines = [];
            $invoiceDetail = $this->lineSource->detail($invoice->id);
            $taxFixed = $this->lineSource->cancellationTax($invoiceDetail);
            foreach ($this->lineSource->cancellationLines($invoiceDetail) as $sort => $line) {
                $lines[] = new InvoiceCorrectionLine(Uuid::v4(), $id, $sort, $line['description'], $line['quantity'], $line['unitPrice']);
            }
        } else {
            $lines = $command->input->parseLines($id, $errors);
            if ($lines === [] && !isset($errors['lines'])) {
                $errors['lines'] = 'Bitte mindestens eine Position erfassen.';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $settings = $this->invoiceSettings->get();
        $now = new DateTimeImmutable();
        $note = $command->input->note !== null && trim($command->input->note) !== '' ? trim($command->input->note) : null;
        $correction = new InvoiceCorrection(
            id: $id,
            invoiceId: $invoice->id,
            type: $command->type,
            status: InvoiceCorrectionStatus::Draft,
            correctionNumber: null,
            correctionDate: null,
            reason: $reason,
            note: $note,
            isSmallBusiness: $invoice->isSmallBusiness ?? $settings->isSmallBusiness,
            vatRatePercent: (string) ($invoice->vatRatePercent ?? $settings->vatRatePercent),
            requiresSettlement: false,
            settledAt: null,
            lines: $lines,
            createdAt: $now,
            updatedAt: $now,
            taxAmountFixed: $taxFixed,
        );
        $this->corrections->save($correction);

        return $id;
    }

    /**
     * @param InvoiceCorrection[] $existing
     */
    public static function assertCorrectable(InvoiceStatus $status, array $existing, InvoiceCorrectionType $type, ?string $ignoreId = null): void
    {
        foreach ($existing as $other) {
            if ($other->id !== $ignoreId && $other->type === InvoiceCorrectionType::Cancellation) {
                throw new ConflictException($other->isDraft()
                    ? 'Für diese Rechnung gibt es bereits einen Stornorechnungs-Entwurf. Bitte diesen ausstellen oder löschen.'
                    : 'Diese Rechnung wurde bereits storniert (' . $other->correctionNumber . ').');
            }
        }
        if ($status === InvoiceStatus::Cancelled) {
            throw new ConflictException('Eine stornierte Rechnung kann nicht mehr korrigiert werden.');
        }
        if (!in_array($status, [InvoiceStatus::Sent, InvoiceStatus::Paid], true)) {
            throw new ConflictException('Korrekturen sind nur für versendete oder bezahlte Rechnungen möglich. Einen Entwurf bitte direkt bearbeiten.');
        }
    }
}
