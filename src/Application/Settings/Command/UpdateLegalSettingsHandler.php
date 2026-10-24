<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\LegalSettings;
use App\Domain\Settings\LegalSettingsRepositoryInterface;

/**
 * Vermietbedingungen sind ausschließlich Markdown (keine Längenprüfung außer
 * einer großzügigen Obergrenze). Impressum/Datenschutz können je nach
 * gewähltem Modus entweder Markdown-Text ODER eine externe URL sein — nur
 * der jeweils aktive Modus wird serverseitig geprüft/gespeichert, das
 * jeweils andere Feld bleibt als Rohwert erhalten, damit beim Umschalten des
 * Modus in der Oberfläche nichts verloren geht.
 */
final class UpdateLegalSettingsHandler implements CommandHandlerInterface
{
    private const MAX_MARKDOWN_LENGTH = 50000;
    private const MAX_URL_LENGTH = 2000;

    public function __construct(private readonly LegalSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateLegalSettings);

        $errors = [];

        $rentalTermsMarkdown = $this->nullIfEmpty($command->rentalTermsMarkdown);
        if ($rentalTermsMarkdown !== null && mb_strlen($rentalTermsMarkdown) > self::MAX_MARKDOWN_LENGTH) {
            $errors['rentalTermsMarkdown'] = 'Die Vermietbedingungen sind zu lang (maximal ' . self::MAX_MARKDOWN_LENGTH . ' Zeichen).';
        }

        [$imprintMode, $imprintMarkdown, $imprintUrl, $imprintErrors] = $this->normalizeField(
            $command->imprintMode,
            $command->imprintMarkdown,
            $command->imprintUrl,
            'imprint',
            'Impressum'
        );
        $errors += $imprintErrors;

        [$privacyMode, $privacyMarkdown, $privacyUrl, $privacyErrors] = $this->normalizeField(
            $command->privacyMode,
            $command->privacyMarkdown,
            $command->privacyUrl,
            'privacy',
            'Datenschutzerklärung'
        );
        $errors += $privacyErrors;

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $settings = new LegalSettings(
            rentalTermsMarkdown: $rentalTermsMarkdown,
            imprintMode: $imprintMode,
            imprintMarkdown: $imprintMarkdown,
            imprintUrl: $imprintUrl,
            privacyMode: $privacyMode,
            privacyMarkdown: $privacyMarkdown,
            privacyUrl: $privacyUrl,
        );

        $this->settingsRepository->save($settings);

        return null;
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string, 3: array<string, string>}
     */
    private function normalizeField(?string $mode, ?string $markdown, ?string $url, string $fieldKey, string $label): array
    {
        $errors = [];

        $mode = $mode === LegalSettings::MODE_LINK ? LegalSettings::MODE_LINK : LegalSettings::MODE_MARKDOWN;

        $markdown = $this->nullIfEmpty($markdown);
        if ($markdown !== null && mb_strlen($markdown) > self::MAX_MARKDOWN_LENGTH) {
            $errors["{$fieldKey}Markdown"] = "Der Text für {$label} ist zu lang (maximal " . self::MAX_MARKDOWN_LENGTH . ' Zeichen).';
        }

        $url = $this->nullIfEmpty($url);
        if ($url !== null) {
            if (mb_strlen($url) > self::MAX_URL_LENGTH) {
                $errors["{$fieldKey}Url"] = "Die URL für {$label} ist zu lang.";
            } elseif ($mode === LegalSettings::MODE_LINK && !$this->isValidAbsoluteUrl($url)) {
                // Nur bei aktivem Link-Modus wird die URL hart geprüft — im
                // Markdown-Modus bleibt ein zuvor eingegebener, ggf. noch
                // unvollständiger Link-Wert unverändert erhalten.
                $errors["{$fieldKey}Url"] = "Bitte eine gültige URL für {$label} angeben (z. B. https://beispiel.de/impressum).";
            }
        }

        return [$mode, $markdown, $url, $errors];
    }

    private function isValidAbsoluteUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return in_array($scheme, ['http', 'https'], true);
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
