-- Migration 140: mehrsprachige Durchsagen und Übersetzer
--  - rental_audio_settings.tts_language: jetzt Liste (kommagetrennt), z. B.
--    "de-DE,en-GB"; leer = Sprache wird in der Live-Oberfläche gewählt
--  - audio_settings: Übersetzer (none | openai | deepl), DeepL-Schlüssel,
--    OpenAI-Modell (der OpenAI-Schlüssel kommt aus den KI-Werbebildern)
ALTER TABLE rental_audio_settings
    MODIFY COLUMN tts_language VARCHAR(255) NULL;
ALTER TABLE audio_settings
    ADD COLUMN translator_provider VARCHAR(10) NOT NULL DEFAULT 'none' AFTER queue_expiry_minutes,
    ADD COLUMN deepl_api_key VARCHAR(255) NULL AFTER translator_provider,
    ADD COLUMN openai_translation_model VARCHAR(60) NOT NULL DEFAULT 'gpt-4o-mini' AFTER deepl_api_key;
