<?php

declare(strict_types=1);

namespace App\Domain\Mail;

interface EmailTemplateRepositoryInterface
{
    /** Liefert die gespeicherte Vorlage, oder die eingebauten Standardwerte (EmailTemplateKey::defaultSubject()/defaultBodyHtml()), falls (noch) keine Zeile existiert. */
    public function get(EmailTemplateKey $key): EmailTemplate;

    /** @return EmailTemplate[] Alle Vorlagen, in der Reihenfolge von EmailTemplateKey::cases(). */
    public function getAll(): array;

    public function save(EmailTemplate $template): void;

    /** Setzt eine Vorlage auf die eingebauten Standardwerte zurück (löscht die Zeile, damit get() wieder auf defaultSubject()/defaultBodyHtml() zurückfällt). */
    public function resetToDefault(EmailTemplateKey $key): void;
}
