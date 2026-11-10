<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

/** Globale Audio-/Durchsage-Einstellungen (Einstellungen › Audio & Durchsagen). */
final class AudioSettings
{
    public function __construct(
        public ?string $gongOriginalFilename = null,
        public ?string $gongStoredFilename = null,
        public ?string $gongMimeType = null,
        public string $ttsLanguage = 'de-DE',
        public ?string $ttsVoice = null,
        public float $ttsRate = 1.0,
        public int $maxUploadMb = 50,
        // Wartende Aufträge, die das Gerät nicht innerhalb dieser Zeit
        // abholt, verfallen — sonst würde ein später eingeschalteter Pi
        // alte Durchsagen nachholen.
        public int $queueExpiryMinutes = 10,
        // Migration 140: Übersetzer für mehrsprachige Durchsagen
        public string $translatorProvider = 'none', // none | openai | deepl
        public ?string $deeplApiKey = null,
        public string $openaiTranslationModel = 'gpt-4o-mini',
    ) {
    }

    public const GONG_DIRECTORY = 'veranstaltungs-audio/gong';
}
