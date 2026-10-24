<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Mail\EmailTemplate;
use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\EmailTemplateRepositoryInterface;
use PDO;

final class MariadbEmailTemplateRepository implements EmailTemplateRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(EmailTemplateKey $key): EmailTemplate
    {
        $stmt = $this->pdo->prepare('SELECT * FROM email_templates WHERE template_key = :template_key');
        $stmt->execute(['template_key' => $key->value]);
        $row = $stmt->fetch();

        if ($row === false) {
            // Noch keine (angepasste) Zeile vorhanden — die eingebauten
            // Standardwerte gelten dann als aktuell gültige Vorlage.
            return new EmailTemplate($key, $key->defaultSubject(), $key->defaultBodyHtml());
        }

        return new EmailTemplate($key, $row['subject'], $row['body_html']);
    }

    /** @return EmailTemplate[] */
    public function getAll(): array
    {
        return array_map(fn (EmailTemplateKey $key) => $this->get($key), EmailTemplateKey::cases());
    }

    public function save(EmailTemplate $template): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_templates (template_key, subject, body_html) VALUES (:template_key, :subject, :body_html)
             ON DUPLICATE KEY UPDATE subject = VALUES(subject), body_html = VALUES(body_html)'
        );

        $stmt->execute([
            'template_key' => $template->key->value,
            'subject' => $template->subject,
            'body_html' => $template->bodyHtml,
        ]);
    }

    public function resetToDefault(EmailTemplateKey $key): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM email_templates WHERE template_key = :template_key');
        $stmt->execute(['template_key' => $key->value]);
    }
}
