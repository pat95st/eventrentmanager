<?php

declare(strict_types=1);

namespace App\Domain\Article;

/**
 * Format/Seitenverhältnis für ein generiertes KI-Werbebild (siehe
 * ArticleAdImage). Wird bei jeder Generierung frei gewählt (siehe
 * App\Application\Article\Command\GenerateArticleAdImage) — kein fester
 * Standard, da unterschiedliche Plattformen (Instagram Post/Story, …)
 * unterschiedliche Formate erwarten. Die Werte entsprechen direkt den von
 * OpenAIs gpt-image-1 unterstützten "size"-Werten (siehe openAiSize()).
 */
enum ArticleAdImageFormat: string
{
    case Square = 'square';       // 1:1 — z. B. Instagram-Feed-Post
    case Portrait = 'portrait';   // 2:3 (hochformat) — z. B. Instagram-/Facebook-Story, Reel
    case Landscape = 'landscape'; // 3:2 (querformat) — z. B. Facebook-Werbeanzeige, Webseiten-Banner

    public function label(): string
    {
        return match ($this) {
            self::Square => '1:1 (Quadratisch, z. B. Feed-Post)',
            self::Portrait => '9:16 (Hochformat, z. B. Story/Reel)',
            self::Landscape => '16:9 (Querformat, z. B. Anzeige/Banner)',
        };
    }

    /** Von OpenAIs gpt-image-1 unterstützter "size"-Parameter (siehe OpenAiImageClient). */
    public function openAiSize(): string
    {
        return match ($this) {
            self::Square => '1024x1024',
            self::Portrait => '1024x1536',
            self::Landscape => '1536x1024',
        };
    }
}
