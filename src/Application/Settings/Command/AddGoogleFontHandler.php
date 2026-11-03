<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Settings\PdfFontRepositoryInterface;
use App\Infrastructure\Fonts\GoogleFontsDownloader;

final class AddGoogleFontHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly PdfFontRepositoryInterface $fonts,
        private readonly GoogleFontsDownloader $downloader,
        private readonly PdfFontStorage $storage,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddGoogleFont);
        $family = preg_replace('/\s+/', ' ', trim($command->family)) ?? '';
        if ($family !== '' && $this->fonts->findByFamily($family) !== null) {
            throw new ConflictException("Die Schrift „{$family}“ ist bereits vorhanden.");
        }

        return $this->storage->store($family, 'google', $this->downloader->download($family))->id;
    }
}
