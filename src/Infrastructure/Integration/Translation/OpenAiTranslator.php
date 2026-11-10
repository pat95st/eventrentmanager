<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Translation;

use App\Application\EventAudio\Translation\TranslationException;
use App\Domain\EventAudio\TtsLanguage;

/** Übersetzung über die OpenAI Chat-Completions-API (Schlüssel aus den KI-Werbebildern). */
final class OpenAiTranslator
{
    private const URL = 'https://api.openai.com/v1/chat/completions';

    public function __construct(private readonly HttpJsonClient $http = new HttpJsonClient())
    {
    }

    public function translate(string $apiKey, string $model, string $text, string $source, string $target): string
    {
        [$status, $json] = $this->http->postJson(self::URL, [
            'model' => $model,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => 'You translate public-address announcements for live events. Translate the user message from '
                    . TtsLanguage::englishName($source) . ' (' . $source . ') to ' . TtsLanguage::englishName($target) . ' (' . $target . '). '
                    . 'Keep it natural and suitable for being read aloud by a speech synthesizer. Keep names, numbers and times. '
                    . 'Reply with the translation only, without quotes or explanations.'],
                ['role' => 'user', 'content' => $text],
            ],
        ], ['Authorization: Bearer ' . trim($apiKey)], 30);
        if ($status === 401) {
            throw new TranslationException('OpenAI lehnt den API-Schlüssel ab.');
        }
        if ($status === 429) {
            throw new TranslationException('OpenAI: Limit erreicht – bitte kurz warten.');
        }
        $translated = $json['choices'][0]['message']['content'] ?? null;
        if ($status !== 200 || !is_string($translated) || trim($translated) === '') {
            $msg = is_array($json['error'] ?? null) ? (string) ($json['error']['message'] ?? '') : '';
            throw new TranslationException('OpenAI konnte den Text nicht übersetzen (HTTP ' . $status . ($msg !== '' ? ': ' . mb_substr($msg, 0, 160) : '') . ').');
        }

        return trim($translated, " \n\r\t\"„“");
    }
}
