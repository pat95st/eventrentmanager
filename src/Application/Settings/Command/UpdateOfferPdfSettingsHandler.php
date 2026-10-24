<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\OfferPdfSettings;
use App\Domain\Settings\OfferPdfSettingsRepositoryInterface;
use App\Infrastructure\RichText\HtmlSanitizer;

/**
 * Beide Felder sind ein vom Rich-Text-Editor geliefertes HTML-Fragment
 * (siehe OfferPdfSettings) — wird hier VOR dem Speichern nochmal serverseitig
 * durch HtmlSanitizer auf die erlaubte Elemente-Allowlist bereinigt, egal ob
 * JavaScript im Browser lief oder der Admin die Textarea (Fallback ohne JS)
 * direkt von Hand befüllt hat. Sonst analog zur vormaligen
 * Markdown-Längenbegrenzung, nur etwas großzügiger, da HTML-Markup Zeichen
 * kostet.
 */
final class UpdateOfferPdfSettingsHandler implements CommandHandlerInterface
{
    private const MAX_HTML_LENGTH = 40000;

    public function __construct(
        private readonly OfferPdfSettingsRepositoryInterface $settingsRepository,
        private readonly HtmlSanitizer $htmlSanitizer,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateOfferPdfSettings);

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

        $this->settingsRepository->save(new OfferPdfSettings(
            introHtml: $this->htmlSanitizer->sanitize($command->introHtml),
            closingHtml: $this->htmlSanitizer->sanitize($command->closingHtml),
        ));

        return null;
    }
}
