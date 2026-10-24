<?php

declare(strict_types=1);

namespace App\Application\Mail;

use App\Domain\Mail\EmailTemplateKey;
use App\Domain\Mail\EmailTemplateRepositoryInterface;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\CompanySettingsRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Mail\MailAttachment;

/**
 * Ersetzt die {{platzhalter}} einer im Admin-Bereich bearbeitbaren
 * E-Mail-Vorlage (siehe EmailTemplateKey/EmailTemplateRepositoryInterface)
 * durch die vom jeweiligen Handler übergebenen Werte und leitet daraus
 * zusätzlich einen Klartext-Fallback ab (siehe RenderedEmail), da
 * SmtpMailer::send() weiterhin einen Text-Body für die
 * multipart/alternative-Nachricht benötigt.
 *
 * Baut außerdem — sofern der (bereits substituierte) HTML-Body eine
 * "cid:company-logo"-Referenz enthält, siehe die "Firmenlogo einfügen"-
 * Schaltfläche in resources/views/settings/email-templates/edit.php — den
 * dafür nötigen Inline-Bildanhang (RenderedEmail::$logoAttachment). Das
 * Firmenlogo wird bewusst hier zentral aufgelöst (statt in jedem einzelnen
 * *Handler), damit jede Vorlage — unabhängig davon, welcher Handler sie
 * versendet — die Schaltfläche gleichermaßen nutzen kann.
 */
final class EmailTemplateRenderer
{
    private const LOGO_CONTENT_ID = 'company-logo';

    public function __construct(
        private readonly EmailTemplateRepositoryInterface $templates,
        private readonly CompanySettingsRepositoryInterface $companySettings,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    /**
     * @param array<string, string> $variables Caller-built, bereits HTML-sichere
     *        Strings (der Aufrufer ist dafür verantwortlich, frei eingegebenen
     *        Text vor dem Einfügen in dieses Array mit e() zu escapen — siehe
     *        die aktualisierten Handler). Wird hier NICHT nochmal escaped.
     */
    public function render(EmailTemplateKey $key, array $variables): RenderedEmail
    {
        $template = $this->templates->get($key);
        $companySettings = $this->companySettings->get();

        if (!array_key_exists('company_name', $variables)) {
            $variables['company_name'] = $companySettings->name ?? '';
        }

        $subject = $this->substitute($template->subject, $variables);
        $html = $this->substitute($template->bodyHtml, $variables);
        $text = $this->deriveTextBody($html);

        $logoAttachment = str_contains($html, 'cid:' . self::LOGO_CONTENT_ID)
            ? $this->buildLogoAttachment($companySettings)
            : null;

        return new RenderedEmail($subject, $html, $text, $logoAttachment);
    }

    /**
     * Liest das hinterlegte Firmenlogo als inline einbettbaren Mail-Anhang
     * ein (Rohbytes, keine Konvertierung wie z. B. bei den PDF-Generatoren
     * nötig — anders als die per SimplePdfWriter erzeugten PDFs stellen
     * normale Mail-Clients JPEG/PNG/WebP/GIF direkt dar). Liefert null,
     * wenn kein Logo hinterlegt ist ODER die Datei kein von
     * getimagesizefromstring() erkanntes Rasterbild ist — insbesondere bei
     * einem SVG-Logo (siehe UploadCompanyLogoHandler::ALLOWED_MIME_TYPES):
     * SVG wird von vielen Mail-Clients (u. a. Outlook) inline nicht
     * dargestellt, daher hier bewusst kein Einbettungsversuch statt eines
     * kaputten Bild-Icons beim Empfänger.
     */
    private function buildLogoAttachment(CompanySettings $companySettings): ?MailAttachment
    {
        if ($companySettings->logoFilename === null) {
            return null;
        }

        $absolutePath = $this->fileStorage->absolutePath('company', $companySettings->logoFilename);
        if (!is_file($absolutePath)) {
            return null;
        }

        $bytes = @file_get_contents($absolutePath);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        $imageInfo = @getimagesizefromstring($bytes);
        if ($imageInfo === false) {
            return null;
        }

        return new MailAttachment(
            filename: basename($companySettings->logoFilename),
            mimeType: $imageInfo['mime'],
            content: $bytes,
            contentId: self::LOGO_CONTENT_ID,
            inline: true,
        );
    }

    /** @param array<string, string> $variables */
    private function substitute(string $template, array $variables): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $matches) => $variables[$matches[1]] ?? '',
            $template
        );
    }

    /** Leitet aus dem substituierten HTML einen brauchbaren Klartext-Fallback ab. */
    private function deriveTextBody(string $html): string
    {
        $withBreaks = preg_replace('#</p>|<br\s*/?>#i', "\n", $html) ?? $html;
        $stripped = strip_tags($withBreaks);
        $decoded = html_entity_decode($stripped, ENT_QUOTES, 'UTF-8');
        $collapsed = preg_replace('/\n{3,}/', "\n\n", $decoded) ?? $decoded;

        return trim($collapsed);
    }
}
