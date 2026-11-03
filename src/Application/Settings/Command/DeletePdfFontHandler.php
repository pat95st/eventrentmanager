<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Settings\PdfFontRepositoryInterface;

/** Zuordnungen fallen per FK auf "Standard" zurück. */
final class DeletePdfFontHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly PdfFontRepositoryInterface $fonts,
        private readonly PdfFontStorage $storage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeletePdfFont);
        $font = $this->fonts->findById($command->id);
        if ($font === null) {
            throw new NotFoundException('Schrift wurde nicht gefunden.');
        }
        $this->fonts->delete($font->id);
        $this->storage->deleteFiles($font);

        return null;
    }
}
