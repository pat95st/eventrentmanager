<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\InvoicePdfSettings;
use App\Domain\Settings\InvoicePdfSettingsRepositoryInterface;
use App\Infrastructure\RichText\HtmlSanitizer;

/**
 * Analog zu UpdateOfferPdfSettingsHandler — siehe dortiger Klassendoc.
 */
final class UpdateInvoicePdfSettingsHandler implements CommandHandlerInterface
{
    private const MAX_HTML_LENGTH = 40000;

    public function __construct(
        private readonly InvoicePdfSettingsRepositoryInterface $settingsRepository,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateInvoicePdfSettings);

        $errors = [];

        if ($command->introHtml !== null && mb_strlen($command->introHtml) > self::MAX_HTML_LENGTH) {
            $errors['introHtml'] = 'Der Einleitungstext ist zu lang (maximal ' . self::MAX_HTML_LENGTH . ' Zeichen).';
        }

        if ($command->closingHtml !== null && mb_strlen($command->closingHtml) > self::MAX_HTML_LENGTH) {
            $errors['closingHtml'] = 'Der Schlusstext ist zu lang (maximal ' . self::MAX_HTML_LENGTH . ' Zeichen).';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->settingsRepository->save(new InvoicePdfSettings(
            introHtml: $this->htmlSanitizer->sanitize($command->introHtml),
            closingHtml: $this->htmlSanitizer->sanitize($command->closingHtml),
        ));

        return null;
    }
}
