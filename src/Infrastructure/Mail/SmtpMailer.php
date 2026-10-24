<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Mail\MailCategory;
use App\Domain\Settings\SmtpEncryption;
use App\Domain\Settings\SmtpSettings;
use App\Domain\Settings\SmtpSettingsRepositoryInterface;

/**
 * Handgeschriebener, abhängigkeitsfreier SMTP-Client über PHP-Sockets
 * (kein PHPMailer/Symfony Mailer — Composer-Pakete sind in dieser Umgebung
 * nicht zuverlässig installierbar, siehe README/Projektdoku). Deckt das ab,
 * was für den Versand von 2FA-E-Mail-Codes, admin-bearbeitbaren HTML-Mails
 * (siehe App\Application\Mail\EmailTemplateRenderer) sowie einfachen
 * Text-Mails mit optionalem Dateianhang (z. B. Packlisten-PDF) benötigt
 * wird: implizites TLS (Port 465) oder STARTTLS (Port 587), optionale
 * AUTH LOGIN-Anmeldung. Kein Connection-Pooling — bewusst minimal für die
 * aktuellen Anwendungsfälle. HTML-Mails werden als multipart/alternative
 * (Text + HTML) verschickt, bei zusätzlichen Anhängen als multipart/mixed
 * mit der multipart/alternative als erstem, verschachteltem Teil; inline
 * eingebettete Bilder (z. B. das per "cid:"-Referenz eingefügte Firmenlogo,
 * siehe MailAttachment::$inline) kommen als zusätzliches verschachteltes
 * multipart/related um die multipart/alternative herum hinzu (siehe
 * buildMultipartMessage()). Alles von Hand nach RFC 2045/2046 gebaut, da
 * keine Mail-Bibliothek zur Verfügung steht.
 */
final class SmtpMailer implements MailerInterface
{
    private const TIMEOUT_SECONDS = 10;

    public function __construct(private readonly SmtpSettingsRepositoryInterface $settingsRepository)
    {
    }

    /** @param MailAttachment[] $attachments */
    public function send(string $toAddress, string $subject, string $textBody, array $attachments = [], ?string $htmlBody = null, ?MailCategory $category = null): void
    {
        $settings = $this->settingsRepository->get();

        if (!$settings->isConfigured()) {
            throw new MailerException('SMTP ist noch nicht konfiguriert (siehe Einstellungen → SMTP).');
        }

        $socket = $this->connect($settings);

        try {
            $this->expect($socket, 220);
            $this->command($socket, 'EHLO materialverleih.local', 250);

            if ($settings->encryption === SmtpEncryption::Tls) {
                $this->command($socket, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new MailerException('STARTTLS-Verhandlung mit dem SMTP-Server ist fehlgeschlagen.');
                }
                // Nach STARTTLS ist ein erneutes EHLO vorgeschrieben (RFC 3207).
                $this->command($socket, 'EHLO materialverleih.local', 250);
            }

            if ($settings->username !== null && $settings->username !== '') {
                $this->command($socket, 'AUTH LOGIN', 334);
                $this->command($socket, base64_encode($settings->username), 334);
                $this->command($socket, base64_encode((string) $settings->password), 235);
            }

            $this->command($socket, 'MAIL FROM:<' . $settings->fromAddress . '>', 250);
            $this->command($socket, 'RCPT TO:<' . $toAddress . '>', 250);

            // Archivierungsadresse (siehe Einstellungen → SMTP): erhält eine
            // stille Kopie jeder ausgehenden Mail, sofern die Kategorie
            // dieser Mail dafür freigegeben ist (siehe
            // SmtpSettings::shouldArchive()) — ein null $category (Aufrufer
            // hat keine Kategorie angegeben) archiviert sicherheitshalber
            // immer, wie vor Einführung der Kategorien. Bewusst NUR als
            // weiterer RCPT TO auf Umschlagsebene, ohne eigenen Bcc-Header —
            // ein Bcc-Header in der eigentlichen Nachricht wäre kein echtes
            // BCC mehr, da er (je nach Mail-Client) allen sichtbaren
            // Empfängern mit angezeigt werden kann.
            $archiveBccAddress = $settings->archiveBccAddress;
            if (
                $archiveBccAddress !== null
                && trim($archiveBccAddress) !== ''
                && strcasecmp(trim($archiveBccAddress), $toAddress) !== 0
                && ($category === null || $settings->shouldArchive($category))
            ) {
                $this->command($socket, 'RCPT TO:<' . trim($archiveBccAddress) . '>', 250);
            }

            $this->command($socket, 'DATA', 354);

            $headerLines = [
                'From: ' . $this->formatAddress($settings->fromName, $settings->fromAddress),
                'To: <' . $toAddress . '>',
            ];

            if ($settings->replyToAddress !== null && trim($settings->replyToAddress) !== '') {
                $headerLines[] = 'Reply-To: <' . trim($settings->replyToAddress) . '>';
            }

            $headerLines[] = 'Subject: ' . $this->encodeSubject($subject);
            $headerLines[] = 'MIME-Version: 1.0';
            $headerLines[] = 'Date: ' . date('r');

            $commonHeaders = implode("\r\n", $headerLines);

            $message = ($attachments === [] && $htmlBody === null)
                ? $this->buildPlainMessage($commonHeaders, $textBody)
                : $this->buildMultipartMessage($commonHeaders, $textBody, $attachments, $htmlBody);

            // Zeilen, die zufällig mit einem Punkt beginnen, müssen laut SMTP
            // "byte-stuffed" werden (führender Punkt verdoppelt), sonst
            // interpretiert der Server die Zeile als Ende der DATA-Sektion.
            $escapedMessage = preg_replace('/^\./m', '..', $message);

            $this->write($socket, $escapedMessage . "\r\n.");
            $this->expect($socket, 250);

            $this->command($socket, 'QUIT', 221);
        } finally {
            fclose($socket);
        }
    }

    /** @return resource */
    private function connect(SmtpSettings $settings)
    {
        $transport = $settings->encryption === SmtpEncryption::Ssl ? 'ssl://' : 'tcp://';
        $target = $transport . $settings->host . ':' . $settings->port;

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($target, $errno, $errstr, self::TIMEOUT_SECONDS);

        if ($socket === false) {
            throw new MailerException("Verbindung zum SMTP-Server fehlgeschlagen: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, self::TIMEOUT_SECONDS);

        return $socket;
    }

    /** @param resource $socket */
    private function command($socket, string $line, int $expectedCode): void
    {
        $this->write($socket, $line);
        $this->expect($socket, $expectedCode);
    }

    /** @param resource $socket */
    private function write($socket, string $line): void
    {
        fwrite($socket, $line . "\r\n");
    }

    /** @param resource $socket */
    private function expect($socket, int $expectedCode): void
    {
        $response = '';
        do {
            $line = fgets($socket, 512);
            if ($line === false) {
                throw new MailerException('SMTP-Server hat die Verbindung unerwartet beendet.');
            }
            $response .= $line;
            // Mehrzeilige Antworten haben einen Bindestrich statt Leerzeichen
            // nach dem Code auf allen Zeilen außer der letzten (RFC 5321).
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($response, 0, 3);
        if ($code !== $expectedCode) {
            throw new MailerException("Unerwartete SMTP-Antwort (erwartet {$expectedCode}): " . trim($response));
        }
    }

    /** Einfache Text-Mail ohne Anhang (bisheriges Verhalten, unverändert). */
    private function buildPlainMessage(string $commonHeaders, string $textBody): string
    {
        $headers = $commonHeaders . "\r\n" . implode("\r\n", [
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ]);

        return $headers . "\r\n\r\n" . $textBody;
    }

    /**
     * Baut eine MIME-Mehrteil-Nachricht von Hand, abhängig davon, ob ein
     * HTML-Body, gewöhnliche (Download-)Anhänge und/oder inline eingebettete
     * Bilder (MailAttachment::$inline, z. B. das per "cid:"-Referenz im
     * HTML-Body eingefügte Firmenlogo) vorliegen:
     *
     * - Nur gewöhnliche Anhänge, kein HTML: multipart/mixed aus Text-Teil +
     *   Anhang-Teilen (bisheriges Verhalten, unverändert).
     * - HTML, keine Anhänge/Inline-Bilder: multipart/alternative aus
     *   Text-Teil + HTML-Teil.
     * - HTML + Inline-Bilder (mit oder ohne gewöhnliche Anhänge): die
     *   multipart/alternative wird zusätzlich von einem multipart/related
     *   umschlossen, das die Inline-Bild-Teile als Geschwister enthält —
     *   Standard-MIME-Aufbau, damit Mail-Clients die "cid:"-Referenzen im
     *   HTML auf die richtigen Teile auflösen (RFC 2387). Gewöhnliche
     *   Anhänge liegen, falls vorhanden, außerhalb davon in einem
     *   umschließenden multipart/mixed.
     * - HTML + nur gewöhnliche Anhänge (keine Inline-Bilder): multipart/mixed,
     *   dessen ERSTER Teil die multipart/alternative ist, gefolgt von den
     *   Anhang-Teilen als Geschwister-Teile (bisheriges Verhalten,
     *   unverändert).
     *
     * Kein externes Paket verfügbar, daher hier bewusst von Hand mit festem
     * CRLF-Boundary-Aufbau umgesetzt.
     *
     * @param MailAttachment[] $attachments
     */
    private function buildMultipartMessage(string $commonHeaders, string $textBody, array $attachments, ?string $htmlBody): string
    {
        $inlineAttachments = array_values(array_filter($attachments, static fn (MailAttachment $a) => $a->inline));
        $regularAttachments = array_values(array_filter($attachments, static fn (MailAttachment $a) => !$a->inline));

        if ($htmlBody !== null) {
            $alternativeBoundary = $this->generateBoundary();
            $alternativeContent = 'Content-Type: multipart/alternative; boundary="' . $alternativeBoundary . '"'
                . "\r\n\r\n" . $this->assembleMultipart($alternativeBoundary, [
                    $this->textPlainPartContent($textBody),
                    $this->textHtmlPartContent($htmlBody),
                ]);

            $content = $alternativeContent;

            if ($inlineAttachments !== []) {
                $relatedBoundary = $this->generateBoundary();
                $relatedPartContents = [$content];
                foreach ($inlineAttachments as $attachment) {
                    $relatedPartContents[] = $this->attachmentPartContent($attachment);
                }

                $content = 'Content-Type: multipart/related; boundary="' . $relatedBoundary . '"'
                    . "\r\n\r\n" . $this->assembleMultipart($relatedBoundary, $relatedPartContents);
            }

            if ($regularAttachments !== []) {
                $mixedBoundary = $this->generateBoundary();
                $mixedPartContents = [$content];
                foreach ($regularAttachments as $attachment) {
                    $mixedPartContents[] = $this->attachmentPartContent($attachment);
                }

                $headers = $commonHeaders . "\r\n" . 'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"';

                return $headers . "\r\n\r\n" . $this->assembleMultipart($mixedBoundary, $mixedPartContents);
            }

            // $content trägt hier bereits seinen eigenen Content-Type-Header
            // (multipart/alternative oder multipart/related) — direkt als
            // Nachrichten-Body verwenden, kein weiteres Umschlagen nötig.
            return $commonHeaders . "\r\n" . $content;
        }

        // Nur Anhänge, kein HTML (bisheriges Verhalten, unverändert) — Inline-
        // Bilder ergeben ohne HTML-Body keinen Sinn (keine "cid:"-Referenz
        // möglich) und werden daher wie gewöhnliche Anhänge mit ausgeliefert.
        $boundary = $this->generateBoundary();
        $headers = $commonHeaders . "\r\n" . 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

        $partContents = [$this->textPlainPartContent($textBody)];
        foreach ($attachments as $attachment) {
            $partContents[] = $this->attachmentPartContent($attachment);
        }

        return $headers . "\r\n\r\n" . $this->assembleMultipart($boundary, $partContents);
    }

    /** Text-Teil (text/plain) einer Mehrteil-Nachricht, ohne umgebende Boundary-Zeilen. */
    private function textPlainPartContent(string $textBody): string
    {
        return implode("\r\n", [
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $textBody,
        ]);
    }

    /** HTML-Teil (text/html) einer Mehrteil-Nachricht, ohne umgebende Boundary-Zeilen. */
    private function textHtmlPartContent(string $htmlBody): string
    {
        return implode("\r\n", [
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $htmlBody,
        ]);
    }

    /**
     * Base64-kodierter Anhang-Teil einer Mehrteil-Nachricht, ohne umgebende
     * Boundary-Zeilen. Bei einem inline eingebetteten Bild (siehe
     * MailAttachment::$inline, z. B. das Firmenlogo) wird zusätzlich ein
     * Content-ID-Header gesetzt (in spitzen Klammern, RFC 2392) und
     * "Content-Disposition: inline" statt "attachment" verwendet, damit
     * Mail-Clients es NICHT als herunterladbaren Datei-Anhang anzeigen,
     * sondern per "cid:<id>"-Referenz im HTML-Body auflösen.
     */
    private function attachmentPartContent(MailAttachment $attachment): string
    {
        $lines = ['Content-Type: ' . $attachment->mimeType . '; name="' . $this->escapeHeaderParameter($attachment->filename) . '"'];

        if ($attachment->inline && $attachment->contentId !== null) {
            $lines[] = 'Content-ID: <' . $attachment->contentId . '>';
        }

        $lines[] = 'Content-Transfer-Encoding: base64';
        $lines[] = 'Content-Disposition: ' . ($attachment->inline ? 'inline' : 'attachment') . '; filename="' . $this->escapeHeaderParameter($attachment->filename) . '"';
        $lines[] = '';
        $lines[] = rtrim(chunk_split(base64_encode($attachment->content)), "\r\n");

        return implode("\r\n", $lines);
    }

    /**
     * Fügt die übergebenen Teil-Inhalte (jeweils ohne umgebende
     * Boundary-Zeilen) zu einem vollständigen Mehrteil-Body zusammen:
     * jeder Teil wird mit "--boundary" eingeleitet, die letzte Zeile
     * schließt mit "--boundary--" ab (RFC 2046).
     *
     * @param string[] $partContents
     */
    private function assembleMultipart(string $boundary, array $partContents): string
    {
        $parts = array_map(fn (string $content) => '--' . $boundary . "\r\n" . $content, $partContents);

        return implode("\r\n", $parts) . "\r\n--" . $boundary . '--';
    }

    private function generateBoundary(): string
    {
        return 'mv-' . bin2hex(random_bytes(16));
    }

    /** Verhindert, dass Anführungszeichen im Dateinamen den Header-Parameter zerschießen. */
    private function escapeHeaderParameter(string $value): string
    {
        return str_replace('"', "'", $value);
    }

    private function formatAddress(string $name, string $address): string
    {
        $name = trim($name);
        if ($name === '') {
            return '<' . $address . '>';
        }

        return $this->encodeSubject($name) . ' <' . $address . '>';
    }

    /** Kodiert Betreff/Namen mit Umlauten MIME-konform (RFC 2047), Rest bleibt lesbarer Klartext. */
    private function encodeSubject(string $value): string
    {
        if (preg_match('/^[\x20-\x7e]*$/', $value) === 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }
}
