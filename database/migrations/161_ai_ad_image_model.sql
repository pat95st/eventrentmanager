-- Migration 161: Selectable OpenAI image model for AI ad images.
--
-- The model used to be hard-coded ("gpt-image-1"), which OpenAI has since
-- retired. NULL means "use the application default"
-- (App\Domain\Settings\AiAdImageSettings::DEFAULT_IMAGE_MODEL).

ALTER TABLE ai_ad_image_settings
    ADD COLUMN image_model VARCHAR(100) NULL AFTER openai_api_key;
