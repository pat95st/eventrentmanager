<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\InvoiceCorrection\InvoiceCorrectionRepositoryInterface;

/** Nur Entwürfe dürfen gelöscht werden; liefert die Rechnungs-ID. */
final class DeleteInvoiceCorrectionHandler implements CommandHandlerInterface
{
    public function __construct(private readonly InvoiceCorrectionRepositoryInterface $corrections)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof DeleteInvoiceCorrection);

        $correction = $this->corrections->findById($command->id);
        if ($correction === null) {
            throw new NotFoundException('Die Rechnungskorrektur wurde nicht gefunden.');
        }
        if (!$correction->isDraft()) {
            throw new ConflictException('Eine ausgestellte Rechnungskorrektur kann nicht gelöscht werden.');
        }
        $this->corrections->delete($correction->id);

        return $correction->invoiceId;
    }
}
