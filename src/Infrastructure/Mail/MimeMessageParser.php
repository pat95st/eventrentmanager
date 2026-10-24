<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use DateTimeImmutable;
use Exception;

/**
 * Handgeschriebener, abhängigkeitsfreier MIME-Parser für eingehende
 * E-Mails, analog im Geist zu SmtpMailer (dort wird eine MIME-Nachricht
 * von Hand GEBAUT, hier wird eine von Hand GELESEN) — kein PHP ext-imap,
 * keine Composer-Bibliothek verfügbar (siehe Projektdoku). Deckt genau das
 * ab, was für den Beleg-Posteingang benötigt wird:
 *
 *  - Kopfzeilen inkl. gefalteter (mehrzeiliger) Header (RFC 5322 §2.2.3).
 *  - RFC-2047-kodierte Wörter in Subject/From ("=?UTF-8?B?...?=" / "?Q?").
 *  - multipart/*-Verschachtelung beliebiger Tiefe über Boundary-Trennung
 *    (RFC 2046), inkl. verschachtelter multipart/alternative innerhalb
 *    multipart/mixed (siehe SmtpMailer::buildMultipartMessage() für den
 *    spiegelbildlichen Aufbau).
 *  - Content-Transfer-Encoding base64/quoted-printable-Dekodierung.
 *
 * Anhänge werden auf die für den Beleg-Posteingang erlaubten Typen
 * (PDF/JPG/PNG) gefiltert — alles andere (Signaturbilder, ICS-Einladungen,
 * ...) wird stillschweigend ignoriert, siehe isAllowedAttachment().
 */
final class MimeMessageParser
{
    private const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public function parse(string $rawMessage): MailboxMessage
    {
        // Header/Body-Trennung: die erste Leerzeile (RFC 5322 §2.1). Rohe
        // Nachrichten von Servern nutzen meist CRLF, tolerieren wir aber
        // auch reines LF (z. B. falls ein Server/Client non-konform ist).
        $normalized = str_replace("\r\n", "\n", $rawMessage);
        $splitPos = strpos($normalized, "\n\n");

        if ($splitPos === false) {
            $headerBlock = $normalized;
            $body = '';
        } else {
            $headerBlock = substr($normalized, 0, $splitPos);
            $body = substr($normalized, $splitPos + 2);
        }

        $headers = $this->parseHeaders($headerBlock);

        $messageId = trim($this->firstHeader($headers, 'message-id') ?? '', "<> \t");
        $fromRaw = $this->firstHeader($headers, 'from') ?? '';
        $fromAddress = $this->extractEmailAddress($fromRaw);
        $subject = $this->firstHeader($headers, 'subject');
        $subject = $subject !== null ? $this->decodeRfc2047($subject) : null;
        $date = $this->parseDate($this->firstHeader($headers, 'date'));

        $attachments = [];
        $this->collectAttachments($headers, $body, $attachments);

        return new MailboxMessage(
            messageId: $messageId !== '' ? $messageId : $this->fallbackMessageId($rawMessage),
            fromAddress: $fromAddress,
            subject: $subject,
            date: $date,
            attachments: $attachments,
        );
    }

    /**
     * Zerlegt einen Header-Block in ein assoziatives Array (Schlüssel in
     * Kleinbuchstaben, mehrere gleichnamige Header würden hier den letzten
     * gewinnen lassen — für unsere Zwecke [Message-ID/From/Subject/Date]
     * irrelevant, da diese Header laut RFC ohnehin nur einmal vorkommen).
     * Gefaltete Zeilen (Fortsetzung beginnt mit Leerzeichen/Tab) werden an
     * die vorherige Kopfzeile angehängt.
     *
     * @return array<string, string>
     */
    private function parseHeaders(string $headerBlock): array
    {
        $lines = explode("\n", $headerBlock);
        $headers = [];
        $currentKey = null;

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            if (($line[0] === ' ' || $line[0] === "\t") && $currentKey !== null) {
                $headers[$currentKey] .= ' ' . trim($line);

                continue;
            }

            $colonPos = strpos($line, ':');
            if ($colonPos === false) {
                continue;
            }

            $key = strtolower(trim(substr($line, 0, $colonPos)));
            $value = trim(substr($line, $colonPos + 1));
            $headers[$key] = $value;
            $currentKey = $key;
        }

        return $headers;
    }

    private function firstHeader(array $headers, string $key): ?string
    {
        return $headers[$key] ?? null;
    }

    /** Extrahiert die reine E-Mail-Adresse aus einem "From"-Header ("Name <adresse>" oder nur "adresse"). */
    private function extractEmailAddress(string $fromHeader): string
    {
        $decoded = $this->decodeRfc2047($fromHeader);

        if (preg_match('/<([^<>]+)>/', $decoded, $matches) === 1) {
            return trim($matches[1]);
        }

        if (preg_match('/[^\s<>"]+@[^\s<>"]+/', $decoded, $matches) === 1) {
            return trim($matches[0]);
        }

        return trim($decoded);
    }

    /**
     * Dekodiert RFC-2047-kodierte Wörter ("=?charset?B?...?=" bzw. "?Q?"),
     * wie sie für nicht-ASCII-Zeichen in Subject/From verwendet werden.
     * Mehrere aufeinanderfolgende kodierte Wörter (getrennt durch reinen
     * Leerraum) werden dabei zusammengeführt, siehe RFC 2047 §6.2.
     */
    private function decodeRfc2047(string $value): string
    {
        $pattern = '/=\?([^?]+)\?([BbQq])\?([^?]*)\?=(?:\s+(?==\?))?/';

        $result = preg_replace_callback($pattern, function (array $m): string {
            $charset = $m[1];
            $encoding = strtoupper($m[2]);
            $encodedText = $m[3];

            if ($encoding === 'B') {
                $decoded = base64_decode($encodedText, true);
                $decoded = $decoded !== false ? $decoded : '';
            } else {
                // Quoted-Printable-Variante innerhalb kodierter Wörter:
                // Unterstriche stehen für Leerzeichen (RFC 2047 §4.2).
                $decoded = quoted_printable_decode(str_replace('_', ' ', $encodedText));
            }

            if (strcasecmp($charset, 'UTF-8') !== 0 && function_exists('mb_convert_encoding')) {
                $converted = @mb_convert_encoding($decoded, 'UTF-8', $charset);
                if ($converted !== false) {
                    $decoded = $converted;
                }
            }

            return $decoded;
        }, $value);

        return $result ?? $value;
    }

    private function parseDate(?string $rawDate): ?DateTimeImmutable
    {
        if ($rawDate === null || trim($rawDate) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($rawDate);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Sammelt rekursiv alle Anhänge einer (Teil-)Nachricht in $attachments
     * (Referenz), gefiltert auf die erlaubten Typen (siehe
     * isAllowedAttachment()). $headers/$body gehören entweder zur
     * Gesamtnachricht oder zu einem einzelnen MIME-Teil.
     *
     * @param array<string, string> $headers
     * @param MailboxAttachment[] $attachments
     */
    private function collectAttachments(array $headers, string $body, array &$attachments): void
    {
        $contentType = $this->firstHeader($headers, 'content-type') ?? 'text/plain';
        [$mimeType, $typeParams] = $this->parseHeaderValueWithParams($contentType);

        if (str_starts_with($mimeType, 'multipart/')) {
            $boundary = $typeParams['boundary'] ?? null;
            if ($boundary === null) {
                return;
            }

            foreach ($this->splitByBoundary($body, $boundary) as $partRaw) {
                $partSplit = strpos($partRaw, "\n\n");
                if ($partSplit === false) {
                    $partHeaders = $this->parseHeaders($partRaw);
                    $partBody = '';
                } else {
                    $partHeaders = $this->parseHeaders(substr($partRaw, 0, $partSplit));
                    $partBody = substr($partRaw, $partSplit + 2);
                }

                $this->collectAttachments($partHeaders, $partBody, $attachments);
            }

            return;
        }

        $disposition = $this->firstHeader($headers, 'content-disposition') ?? '';
        [$dispositionType, $dispositionParams] = $this->parseHeaderValueWithParams($disposition);

        $filename = $dispositionParams['filename'] ?? $typeParams['name'] ?? null;
        $filename = $filename !== null ? $this->decodeRfc2047($filename) : null;

        // Als Anhang zählt hier bewusst jeder Teil mit
        // "Content-Disposition: attachment" ODER jeder Teil mit einem
        // Dateinamen, dessen MIME-Type nicht text/* bzw. multipart/* ist
        // (manche Mail-Clients setzen bei Bilddateien kein explizites
        // "attachment", sondern nur einen Dateinamen im Content-Type).
        $looksLikeAttachment = strtolower($dispositionType) === 'attachment'
            || ($filename !== null && !str_starts_with($mimeType, 'text/'));

        if (!$looksLikeAttachment || $filename === null) {
            return;
        }

        if (!$this->isAllowedAttachment($mimeType, $filename)) {
            return;
        }

        $encoding = strtolower($this->firstHeader($headers, 'content-transfer-encoding') ?? '7bit');
        $decodedBody = $this->decodeBody($body, $encoding);

        $attachments[] = new MailboxAttachment(
            filename: $filename,
            mimeType: $this->normalizeMimeType($mimeType, $filename),
            rawBytes: $decodedBody,
        );
    }

    private function isAllowedAttachment(string $mimeType, string $filename): bool
    {
        if (in_array(strtolower($mimeType), self::ALLOWED_MIME_TYPES, true)) {
            return true;
        }

        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, self::ALLOWED_EXTENSIONS, true);
    }

    /**
     * Manche Mail-Clients senden PDFs/Bilder mit einem unspezifischen
     * MIME-Type wie "application/octet-stream" — die Dateiendung ist in
     * diesem Fall zuverlässiger für die spätere Speicherung/Anzeige.
     */
    private function normalizeMimeType(string $mimeType, string $filename): string
    {
        if (in_array(strtolower($mimeType), self::ALLOWED_MIME_TYPES, true)) {
            return strtolower($mimeType);
        }

        return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => $mimeType,
        };
    }

    private function decodeBody(string $body, string $encoding): string
    {
        return match ($encoding) {
            'base64' => (string) base64_decode(preg_replace('/\s+/', '', $body) ?? '', true),
            'quoted-printable' => quoted_printable_decode($body),
            default => $body,
        };
    }

    /**
     * Zerlegt einen Multipart-Body an den "--boundary"-Trennzeilen in seine
     * einzelnen Teile (RFC 2046 §5.1). Der Teil vor der ersten Boundary
     * (Präambel) sowie alles nach der Abschluss-Boundary "--boundary--"
     * (Epilog) werden verworfen.
     *
     * @return string[]
     */
    private function splitByBoundary(string $body, string $boundary): array
    {
        $escapedBoundary = preg_quote($boundary, '/');
        $segments = preg_split('/\n--' . $escapedBoundary . '(?:--)?\n?/', $body);

        if ($segments === false || count($segments) < 2) {
            return [];
        }

        // Erstes Segment ist die Präambel (vor der ersten Boundary), letztes
        // ggf. der Epilog (nach der Abschluss-Boundary) — beide verwerfen.
        array_shift($segments);
        array_pop($segments);

        return array_map(static fn (string $s) => trim($s, "\n"), $segments);
    }

    /**
     * @param string $headerValue z. B. "multipart/mixed; boundary=\"abc\""
     * @return array{0: string, 1: array<string, string>}
     */
    private function parseHeaderValueWithParams(string $headerValue): array
    {
        $parts = explode(';', $headerValue);
        $mainValue = strtolower(trim(array_shift($parts) ?? ''));

        $params = [];
        foreach ($parts as $part) {
            if (!str_contains($part, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $part, 2);
            $key = strtolower(trim($key));
            $value = trim($value);
            $value = trim($value, "\"'");
            $params[$key] = $value;
        }

        return [$mainValue, $params];
    }

    /**
     * Fallback, falls eine Nachricht (nicht RFC-konform) gar keine
     * Message-ID mitliefert: ein deterministischer Hash des Rohinhalts,
     * damit Dedup (siehe receipt_inbox_messages.message_id) weiterhin
     * funktioniert — dieselbe Nachricht ergibt beim nächsten Abruf wieder
     * denselben Wert.
     */
    private function fallbackMessageId(string $rawMessage): string
    {
        return 'sha256:' . hash('sha256', $rawMessage);
    }
}
