<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

final class SaveAudioSettings implements CommandInterface
{
    public function __construct(
        public readonly string $ttsLanguage,
        public readonly ?string $ttsVoice,
        public readonly float $ttsRate,
        public readonly int $maxUploadMb,
        public readonly int $queueExpiryMinutes,
        // Migration 140: Übersetzer; null = unverändert lassen
        public readonly ?string $translatorProvider = null,
        public readonly ?string $deeplApiKey = null, // leer = bisherigen Schlüssel behalten
        public readonly ?string $openaiTranslationModel = null,
        public readonly bool $removeDeeplApiKey = false,
    ) {
    }
}
