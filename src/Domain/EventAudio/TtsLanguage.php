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

    private const ENGLISH = [
        'de' => 'German', 'en' => 'English', 'fr' => 'French', 'it' => 'Italian', 'es' => 'Spanish', 'nl' => 'Dutch',
        'pl' => 'Polish', 'cs' => 'Czech', 'da' => 'Danish', 'sv' => 'Swedish', 'pt' => 'Portuguese', 'tr' => 'Turkish',
        'uk' => 'Ukrainian', 'ru' => 'Russian',
    ];

    /** Englischer Sprachname (für Übersetzer-Prompts). */
    public static function englishName(string $code): string
    {
        $base = self::ENGLISH[strtolower(substr($code, 0, 2))] ?? $code;

        return match ($code) {
            'en-GB' => 'British English',
            'en-US' => 'American English',
            default => $base,
        };
    }

    /** Kurzbezeichnung für Beschriftungen, z. B. „DE“, „EN-GB“. */
    public static function short(string $code): string
    {
        return in_array($code, ['en-GB', 'en-US'], true) ? strtoupper($code) : strtoupper(substr($code, 0, 2));
    }

    public static function label(string $code): string
    {
        return self::OPTIONS[$code] ?? $code;
    }
}
