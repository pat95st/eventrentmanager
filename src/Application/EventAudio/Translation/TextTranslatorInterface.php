<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Translation;

/** Übersetzt Durchsagen-Texte (Migration 140). */
interface TextTranslatorInterface
{
    /** Ist ein Übersetzer eingerichtet (Anbieter + Schlüssel)? */
    public function isAvailable(): bool;

    /** Name des Anbieters für die Anzeige, z. B. „DeepL“. */
    public function providerLabel(): string;

    /**
     * @param string $sourceLanguage BCP-47, z. B. de-DE
     * @param string $targetLanguage BCP-47, z. B. en-GB
     * @throws TranslationException
     */
    public function translate(string $text, string $sourceLanguage, string $targetLanguage): string;
}
