<?php

declare(strict_types=1);

namespace App\Application\Messaging;

use App\Domain\Messaging\MessageTemplateKey;
use App\Domain\Messaging\MessageTemplateRepositoryInterface;
use App\Domain\Messaging\MessagingChannel;

/**
 * Ersetzt die {{platzhalter}} einer Telegram-/Signal-Vorlage durch die vom
 * jeweiligen Handler übergebenen Werte (bewusst analog zu
 * App\Application\Mail\EmailTemplateRenderer, aber ohne HTML/Logo-Anhang —
 * reiner Klartext). Für WhatsApp gibt es KEINE Text-Substitution: dort wird
 * nur die bei Meta genehmigte Vorlage referenziert und die Parameter werden
 * in der vom Code vorgegebenen Reihenfolge (siehe
 * MessageTemplateKey::whatsappParameterOrder()) positional übergeben, siehe
 * resolveWhatsappParameters().
 */
final class MessagingTemplateRenderer
{
    public function __construct(private readonly MessageTemplateRepositoryInterface $templates)
    {
    }

    /** @param array<string, string> $variables */
    public function renderPlainText(MessagingChannel $channel, MessageTemplateKey $key, array $variables): string
    {
        $template = $this->templates->get($channel, $key);
        $body = $template->bodyText ?? $key->defaultBodyText();

        $substituted = (string) preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $matches) => $variables[$matches[1]] ?? '',
            $body
        );

        // Mehrfache Leerzeilen (z. B. wenn warehouse_line leer ist) auf
        // maximal eine zusammenfassen, analog EmailTemplateRenderer::deriveTextBody().
        return trim((string) preg_replace('/\n{3,}/', "\n\n", $substituted));
    }

    /**
     * @param array<string, string> $variables
     * @return array{templateName: string, languageCode: string, parameters: string[]}
     */
    public function resolveWhatsappParameters(MessageTemplateKey $key, array $variables): array
    {
        $template = $this->templates->get(MessagingChannel::WhatsApp, $key);

        $parameters = array_map(
            static fn (string $variableKey) => $variables[$variableKey] ?? '',
            $key->whatsappParameterOrder(),
        );

        return [
            'templateName' => (string) $template->metaTemplateName,
            'languageCode' => $template->metaTemplateLanguage,
            'parameters' => $parameters,
        ];
    }
}
