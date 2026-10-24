<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Query\QueryBus;
use App\Application\Settings\Query\GetLegalSettings;
use App\Domain\Settings\LegalSettings;
use App\Infrastructure\Http\MarkdownRenderer;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\View;

/**
 * Öffentliche, nicht anmeldepflichtige Seiten für Impressum und
 * Datenschutzerklärung (siehe auch Footer-Links in layout.php). Wird nur
 * angesteuert, wenn der jeweilige Text als Markdown gepflegt ist — im
 * Link-Modus verweist der Footer direkt auf die externe URL.
 */
final class LegalPageController
{
    public function __construct(private readonly QueryBus $queries)
    {
    }

    public function imprint(Request $request): void
    {
        $settings = $this->queries->ask(new GetLegalSettings());

        echo View::page('legal/imprint', [
            'html' => MarkdownRenderer::toHtml($this->markdownFor($settings->imprintMode, $settings->imprintMarkdown)),
        ], activeNav: '', title: 'Impressum');
    }

    public function privacy(Request $request): void
    {
        $settings = $this->queries->ask(new GetLegalSettings());

        echo View::page('legal/privacy', [
            'html' => MarkdownRenderer::toHtml($this->markdownFor($settings->privacyMode, $settings->privacyMarkdown)),
        ], activeNav: '', title: 'Datenschutzerklärung');
    }

    private function markdownFor(string $mode, ?string $markdown): ?string
    {
        return $mode === LegalSettings::MODE_MARKDOWN ? $markdown : null;
    }
}
