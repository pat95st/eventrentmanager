<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InvoiceCorrection\InvoiceCorrectionRepositoryInterface;
use DateTimeImmutable;

/** Erstattung/Nachzahlung einer Korrektur zu einer bezahlten Rechnung erledigt. */
final class MarkInvoiceCorrectionSettledHandler implements CommandHandlerInterface
{
    public function __construct(private readonly InvoiceCorrectionRepositoryInterface $corrections)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MarkInvoiceCorrectionSettled);

        $correction = $this->corrections->findById($command->id);
        if ($correction === null) {
            throw new NotFoundException('Die Rechnungskorrektur wurde nicht gefunden.');
        }
        if (!$correction->awaitsSettlement()) {
            throw new ConflictException('Für diese Rechnungskorrektur ist keine Erstattung oder Nachzahlung offen.');
        }

        $raw = $command->settledAt !== null ? trim($command->settledAt) : '';
        $date = $raw === '' ? new DateTimeImmutable('today') : DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($date === false || $date > new DateTimeImmutable('today')) {
            throw new ValidationException(['settled_at' => 'Bitte ein gültiges Datum angeben (nicht in der Zukunft).']);
        }
        if ($correction->correctionDate !== null && $date < $correction->correctionDate) {
            throw new ValidationException(['settled_at' => 'Das Datum darf nicht vor dem Korrekturdatum liegen.']);
        }

        $correction->settledAt = $date;
        $correction->updatedAt = new DateTimeImmutable();
        $this->corrections->save($correction);

        return null;
    }
}
