<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\InvoiceCorrection\InvoiceCorrectionRepositoryInterface;
use App\Domain\InvoiceCorrection\InvoiceCorrectionType;
use DateTimeImmutable;

/** Entwurf bearbeiten; ausgestellte Korrekturen sind unveränderlich. */
final class UpdateInvoiceCorrectionHandler implements CommandHandlerInterface
{
    public function __construct(private readonly InvoiceCorrectionRepositoryInterface $corrections)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateInvoiceCorrection);

        $correction = $this->corrections->findById($command->id);
        if ($correction === null) {
            throw new NotFoundException('Die Rechnungskorrektur wurde nicht gefunden.');
        }
        if (!$correction->isDraft()) {
            throw new ConflictException('Eine ausgestellte Rechnungskorrektur kann nicht mehr geändert werden.');
        }

        $errors = [];
        $reason = trim($command->input->reason);
        if ($reason === '') {
            $errors['reason'] = 'Bitte den Grund der Korrektur angeben (erscheint auf dem Dokument).';
        }
        $lines = $correction->lines;
        if ($correction->type === InvoiceCorrectionType::Partial) {
            $lines = $command->input->parseLines($correction->id, $errors);
            if ($lines === [] && !isset($errors['lines'])) {
                $errors['lines'] = 'Bitte mindestens eine Position erfassen.';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $correction->reason = $reason;
        $correction->note = $command->input->note !== null && trim($command->input->note) !== '' ? trim($command->input->note) : null;
        $correction->lines = $lines;
        $correction->updatedAt = new DateTimeImmutable();
        $this->corrections->save($correction);

        return null;
    }
}
