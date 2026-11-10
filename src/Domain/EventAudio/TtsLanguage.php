<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

/** Auswählbare Sprachen für getippte Durchsagen (Migration 138). */
final class TtsLanguage
{
    /** @var array<string, string> BCP-47-Code => Bezeichnung */
    public const OPTIONS = [
        'de-DE' => 'Deutsch',
        'en-GB' => 'Englisch (UK)',
        'en-US' => 'Englisch (US)',
        'fr-FR' => 'Französisch',
        'it-IT' => 'Italienisch',
        'es-ES' => 'Spanisch',
        'nl-NL' => 'Niederländisch',
        'pl-PL' => 'Polnisch',
        'cs-CZ' => 'Tschechisch',
        'da-DK' => 'Dänisch',
        'sv-SE' => 'Schwedisch',
        'pt-PT' => 'Portugiesisch',
        'tr-TR' => 'Türkisch',
        'uk-UA' => 'Ukrainisch',
        'ru-RU' => 'Russisch',
    ];

    public static function isValid(?string $code): bool
    {
        return $code !== null && preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $code) === 1;
    }

    public static function label(string $code): string
    {
        return self::OPTIONS[$code] ?? $code;
    }
}
