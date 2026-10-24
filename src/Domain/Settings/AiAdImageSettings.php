<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Zugangsdaten für den KI-Werbebildgenerator (siehe
 * App\Application\Article\Command\GenerateArticleAdImageHandler) — Singleton-
 * Zeile analog zu App\Domain\Settings\GoogleMerchantSettings. Anthropic/Claude
 * bietet bewusst KEINE Bildgenerierung an (nur Text), daher ausschließlich
 * ein OpenAI-API-Key (gpt-image-1, Endpunkte "images/generations" und
 * "images/edits" — siehe App\Infrastructure\Integration\OpenAi\OpenAiImageClient).
 */
final class AiAdImageSettings
{
    public function __construct(
        public ?string $openaiApiKey = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->openaiApiKey !== null && trim($this->openaiApiKey) !== '';
    }
}
