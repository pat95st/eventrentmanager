<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Zugangsdaten für den KI-Werbebildgenerator (siehe
 * App\Application\Article\Command\GenerateArticleAdImageHandler) — Singleton-
 * Zeile analog zu App\Domain\Settings\GoogleMerchantSettings. Anthropic/Claude
 * bietet bewusst KEINE Bildgenerierung an (nur Text), daher ausschließlich
 * ein OpenAI-API-Key (Endpunkte "images/generations" und "images/edits" —
 * siehe App\Infrastructure\Integration\OpenAi\OpenAiImageClient).
 *
 * The image model is configurable because OpenAI retires models over time
 * (e.g. "gpt-image-1"); null falls back to DEFAULT_IMAGE_MODEL.
 */
final class AiAdImageSettings
{
    public const DEFAULT_IMAGE_MODEL = 'gpt-image-1.5';

    /** Offered in the settings form when the model list cannot be fetched from OpenAI. */
    public const SUGGESTED_IMAGE_MODELS = ['gpt-image-1.5', 'gpt-image-1-mini'];

    public function __construct(
        public ?string $openaiApiKey = null,
        public ?string $imageModel = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->openaiApiKey !== null && trim($this->openaiApiKey) !== '';
    }

    public function effectiveImageModel(): string
    {
        $model = $this->imageModel !== null ? trim($this->imageModel) : '';

        return $model !== '' ? $model : self::DEFAULT_IMAGE_MODEL;
    }
}
