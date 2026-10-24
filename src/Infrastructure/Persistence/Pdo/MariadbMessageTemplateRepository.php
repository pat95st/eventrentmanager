<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Messaging\MessageTemplate;
use App\Domain\Messaging\MessageTemplateKey;
use App\Domain\Messaging\MessageTemplateRepositoryInterface;
use App\Domain\Messaging\MessagingChannel;
use PDO;

final class MariadbMessageTemplateRepository implements MessageTemplateRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(MessagingChannel $channel, MessageTemplateKey $key): MessageTemplate
    {
        $stmt = $this->pdo->prepare('SELECT * FROM message_templates WHERE channel = :channel AND template_key = :template_key');
        $stmt->execute(['channel' => $channel->value, 'template_key' => $key->value]);
        $row = $stmt->fetch();

        if ($row === false) {
            // Noch keine (angepasste) Zeile vorhanden — eingebaute
            // Standardwerte gelten dann als aktuell gültige Vorlage (bei
            // WhatsApp: leer, da es dafür keinen sinnvollen Standard gibt —
            // die Vorlage muss ohnehin erst bei Meta angelegt werden).
            return new MessageTemplate($channel, $key, bodyText: $key->defaultBodyText());
        }

        return new MessageTemplate(
            $channel,
            $key,
            bodyText: $row['body_text'],
            metaTemplateName: $row['meta_template_name'],
            metaTemplateLanguage: $row['meta_template_language'] ?? 'de',
        );
    }

    public function getAllForChannel(MessagingChannel $channel): array
    {
        return array_values(array_map(
            fn (MessageTemplateKey $key) => $this->get($channel, $key),
            array_filter(MessageTemplateKey::cases(), fn (MessageTemplateKey $key) => $key->appliesToChannel($channel)),
        ));
    }

    public function save(MessageTemplate $template): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO message_templates (channel, template_key, body_text, meta_template_name, meta_template_language)
             VALUES (:channel, :template_key, :body_text, :meta_template_name, :meta_template_language)
             ON DUPLICATE KEY UPDATE
                body_text = VALUES(body_text),
                meta_template_name = VALUES(meta_template_name),
                meta_template_language = VALUES(meta_template_language)'
        );

        $stmt->execute([
            'channel' => $template->channel->value,
            'template_key' => $template->key->value,
            'body_text' => $template->bodyText,
            'meta_template_name' => $template->metaTemplateName,
            'meta_template_language' => $template->metaTemplateLanguage,
        ]);
    }

    public function resetToDefault(MessagingChannel $channel, MessageTemplateKey $key): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM message_templates WHERE channel = :channel AND template_key = :template_key');
        $stmt->execute(['channel' => $channel->value, 'template_key' => $key->value]);
    }
}
