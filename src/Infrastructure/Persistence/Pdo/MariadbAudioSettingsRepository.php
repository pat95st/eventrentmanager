<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\EventAudio\AudioSettings;
use App\Domain\EventAudio\AudioSettingsRepositoryInterface;
use PDO;

final class MariadbAudioSettingsRepository implements AudioSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): AudioSettings
    {
        $row = $this->pdo->query('SELECT * FROM audio_settings WHERE id = 1')->fetch();
        if (!$row) {
            return new AudioSettings();
        }

        return new AudioSettings(
            gongOriginalFilename: $row['gong_original_filename'],
            gongStoredFilename: $row['gong_stored_filename'],
            gongMimeType: $row['gong_mime_type'],
            ttsLanguage: (string) $row['tts_language'],
            ttsVoice: $row['tts_voice'],
            ttsRate: (float) $row['tts_rate'],
            maxUploadMb: (int) $row['max_upload_mb'],
            queueExpiryMinutes: (int) $row['queue_expiry_minutes'],
            translatorProvider: (string) ($row['translator_provider'] ?? 'none'),
            deeplApiKey: $row['deepl_api_key'] ?? null,
            openaiTranslationModel: (string) ($row['openai_translation_model'] ?? 'gpt-4o-mini'),
        );
    }

    public function save(AudioSettings $settings): void
    {
        $this->pdo->prepare(
            'INSERT INTO audio_settings (id, gong_original_filename, gong_stored_filename, gong_mime_type, tts_language, tts_voice, tts_rate, max_upload_mb, queue_expiry_minutes, translator_provider, deepl_api_key, openai_translation_model, updated_at)
             VALUES (1, :orig, :stored, :mime, :lang, :voice, :rate, :max, :expiry, :tprov, :deepl, :omodel, NOW())
             ON DUPLICATE KEY UPDATE gong_original_filename = VALUES(gong_original_filename), gong_stored_filename = VALUES(gong_stored_filename),
                gong_mime_type = VALUES(gong_mime_type), tts_language = VALUES(tts_language), tts_voice = VALUES(tts_voice), tts_rate = VALUES(tts_rate),
                max_upload_mb = VALUES(max_upload_mb), queue_expiry_minutes = VALUES(queue_expiry_minutes),
                translator_provider = VALUES(translator_provider), deepl_api_key = VALUES(deepl_api_key), openai_translation_model = VALUES(openai_translation_model), updated_at = NOW()'
        )->execute([
            'orig' => $settings->gongOriginalFilename,
            'stored' => $settings->gongStoredFilename,
            'mime' => $settings->gongMimeType,
            'lang' => $settings->ttsLanguage,
            'voice' => $settings->ttsVoice,
            'rate' => number_format($settings->ttsRate, 2, '.', ''),
            'max' => $settings->maxUploadMb,
            'expiry' => $settings->queueExpiryMinutes,
            'tprov' => $settings->translatorProvider,
            'deepl' => $settings->deeplApiKey,
            'omodel' => $settings->openaiTranslationModel,
        ]);
    }
}
