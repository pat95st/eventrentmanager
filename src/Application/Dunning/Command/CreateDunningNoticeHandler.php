<?php

declare(strict_types=1);

namespace App\Application\Dunning\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Invoice\Query\GetInvoice;
use App\Application\Invoice\Query\GetInvoiceHandler;
use App\Domain\Dunning\DunningFeeType;
use App\Domain\Dunning\DunningLevelRepositoryInterface;
use App\Domain\Dunning\DunningNotice;
use App\Domain\Dunning\DunningNoticeRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Invoice\InvoiceStatus;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;
use App\Infrastructure\Persistence\Pdo\DunningNumberSequence;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Erstellt eine konkrete Mahnung für eine überfällige Rechnung — analog
 * MarkInvoiceSentHandler: Nummernvergabe (DunningNumberSequence) und
 * Speichern laufen in derselben Transaktion. Rechnungsbetrag und
 * Stufen-Angaben (Name/Fristtage/Gebühr) werden zum Zeitpunkt der Erstellung
 * vollständig auf die Mahnung gesnapshotet (Snapshot-Prinzip, siehe
 * DunningLevel-Klassendoc) — die eigentliche Gebührenberechnung (fest/
 * prozentual vom Bruttorechnungsbetrag) passiert genau hier, einmalig.
 *
 * "Überfällig" bedeutet hier bewusst eng: die Rechnung muss finalisiert
 * (Status "Versendet", also eine Rechnungsnummer + ein Fälligkeitsdatum
 * besitzen) UND das Fälligkeitsdatum muss in der Vergangenheit liegen. Eine
 * bereits bezahlte oder stornierte Rechnung kann nie gemahnt werden.
 */
final class CreateDunningNoticeHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly GetInvoiceHandler $getInvoiceHandler,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
        private readonly DunningLevelRepositoryInterface $dunningLevels,
        private readonly DunningNoticeRepositoryInterface $dunningNotices,
        private readonly DunningNumberSequence $numberSequence,
        private readonly PDO $pdo,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateDunningNotice);

        $detail = $this->getInvoiceHandler->handle(new GetInvoice($command->invoiceId));
        $invoice = $detail->invoice;

        if ($invoice->status !== InvoiceStatus::Sent || $invoice->dueDate === null) {
            throw new ValidationException(['status' => 'Für diese Rechnung kann keine Mahnung erstellt werden — sie ist nicht als "Versendet" finalisiert.']);
        }

        $today = new DateTimeImmutable('today');
        if ($invoice->dueDate >= $today) {
            throw new ValidationException(['status' => 'Diese Rechnung ist noch nicht überfällig (Fälligkeitsdatum ist noch nicht erreicht).']);
        }

        $level = $this->dunningLevels->findById($command->dunningLevelId);
        if ($level === null) {
            throw new ValidationException(['dunningLevelId' => 'Bitte eine gültige Mahnstufe wählen.']);
        }

        $invoiceSettings = $this->invoiceSettings->get();
        $grossTotal = $detail->totals($invoiceSettings)->grossTotal;

        $feeAmount = match ($level->feeType) {
            DunningFeeType::None => 0.0,
            DunningFeeType::Fixed => (float) $level->feeValue,
            DunningFeeType::Percent => round($grossTotal * ((float) $level->feeValue) / 100, 2),
        };

        $now = new DateTimeImmutable();

        $this->pdo->beginTransaction();

        try {
            $dunningNumber = $this->numberSequence->allocateNext();

            $notice = new DunningNotice(
                id: Uuid::v4(),
                invoiceId: $invoice->id,
                dunningNumber: $dunningNumber,
                dunningLevelId: $level->id,
                levelName: $level->name,
                daysAfterDueSnapshot: $level->daysAfterDue,
                feeType: $level->feeType,
                feeValue: $level->feeValue,
                feeAmount: number_format($feeAmount, 2, '.', ''),
                invoiceGrossTotal: number_format($grossTotal, 2, '.', ''),
                createdAt: $now,
                sentAt: null,
                createdBy: $command->createdBy,
            );

            $this->dunningNotices->save($notice);

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        return $notice->id;
    }
}
