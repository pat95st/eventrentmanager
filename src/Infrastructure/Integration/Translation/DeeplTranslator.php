<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration\Translation;

use App\Application\EventAudio\Translation\TranslationException;

/**
 * DeepL-API (v2). Schlüssel mit Endung „:fx“ gehören zum kostenlosen Tarif
 * und nutzen api-free.deepl.com.
 */
final class DeeplTranslator
{
    public function __construct(private readonly HttpJsonClient $http = new HttpJsonClient())
    {
    }

    public function translate(string $apiKey, string $text, string $source, string $target): string
    {
        $url = str_ends_with(trim($apiKey), ':fx') ? 'https://api-free.deepl.com/v2/translate' : 'https://api.deepl.com/v2/translate';
        [$status, $json] = $this->http->postJson($url, [
            'text' => [$text],
            'source_lang' => strtoupper(substr($source, 0, 2)),
            'target_lang' => self::targetCode($target),
        ], ['Authorization: DeepL-Auth-Key ' . trim($apiKey)]);
        if ($status === 403) {
            throw new TranslationException('DeepL lehnt den API-Schlüssel ab.');
        }
        if ($status === 456) {
            throw new TranslationException('Das DeepL-Kontingent ist aufgebraucht.');
        }
        $translated = $json['translations'][0]['text'] ?? null;
        if ($status !== 200 || !is_string($translated)) {
            throw new TranslationException('DeepL konnte den Text nicht übersetzen (HTTP ' . $status . ').');
        }

        return $translated;
    }

    /** DeepL erwartet bei Englisch/Portugiesisch die Variante (EN-GB, EN-US, PT-PT, PT-BR), sonst nur die Sprache. */
    public static function targetCode(string $bcp47): string
    {
        $upper = strtoupper($bcp47);

        return match (true) {
            str_starts_with($upper, 'EN') => in_array($upper, ['EN-US', 'EN-GB'], true) ? $upper : 'EN-GB',
            str_starts_with($upper, 'PT') => $upper === 'PT-BR' ? 'PT-BR' : 'PT-PT',
            default => substr($upper, 0, 2),
        };
    }
}
