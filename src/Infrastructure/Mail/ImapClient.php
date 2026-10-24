<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Settings\MailboxEncryption;
use App\Domain\Settings\MailboxSettings;

/**
 * Handgeschriebener, abhängigkeitsfreier IMAP4rev1-Client über rohe
 * PHP-Sockets (RFC 3501) — analog im Aufbau zu SmtpMailer: kein ext-imap
 * (auf vielen Hosting-Umgebungen/neueren PHP-Versionen nicht verfügbar),
 * kein Composer-Paket (in dieser Umgebung nicht zuverlässig installierbar,
 * siehe SmtpMailer-Kommentar). Deckt bewusst nur das ab, was der
 * Beleg-Posteingang braucht:
 *
 *  - Verbindungsaufbau mit implizitem TLS (ssl://) oder STARTTLS, oder
 *    unverschlüsselt.
 *  - LOGIN mit Benutzername/Passwort.
 *  - SELECT des konfigurierten Ordners.
 *  - SEARCH ALL (bewusst NICHT UNSEEN — der Lesestatus wird nie verändert
 *    und ist für unser rein DB-seitiges Dedup irrelevant).
 *  - FETCH der vollständigen Rohnachricht (BODY[]) je gefundener
 *    Nachrichtennummer, zum lokalen Parsen durch MimeMessageParser.
 *
 * IMAP-Antworten sind zeilenbasiert, können aber sogenannte "Literale"
 * ({n} am Zeilenende, gefolgt von genau n rohen Bytes) enthalten — siehe
 * readResponseLines() für das korrekte, byte-genaue Einlesen statt eines
 * naiven zeilenweisen fgets().
 */
final class ImapClient implements MailboxClientInterface
{
    private const TIMEOUT_SECONDS = 15;

    /** @var resource|null */
    private $socket = null;
    private int $tagCounter = 0;

    public function __construct(private readonly MailboxSettings $settings)
    {
    }

    public function fetchMessages(): array
    {
        $this->connect();

        try {
            $this->login();
            $this->select($this->settings->imapFolder !== '' ? $this->settings->imapFolder : 'INBOX');
            $sequenceNumbers = $this->searchAll();

            $parser = new MimeMessageParser();
            $messages = [];

            foreach ($sequenceNumbers as $sequenceNumber) {
                $raw = $this->fetchRawMessage($sequenceNumber);
                if ($raw === null) {
                    continue;
                }

                $messages[] = $parser->parse($raw);
            }

            $this->logout();

            return $messages;
        } finally {
            $this->close();
        }
    }

    /** Baut nur die Verbindung auf und meldet sich an — für den "Verbindung testen"-Knopf im Admin-Bereich, OHNE Nachrichten abzurufen. */
    public function testConnection(): void
    {
        $this->connect();

        try {
            $this->login();
            $this->select($this->settings->imapFolder !== '' ? $this->settings->imapFolder : 'INBOX');
            $this->logout();
        } finally {
            $this->close();
        }
    }

    private function connect(): void
    {
        $host = $this->settings->host ?? '';
        $port = $this->settings->port ?? ($this->settings->encryption === MailboxEncryption::Ssl ? 993 : 143);

        $transport = $this->settings->encryption === MailboxEncryption::Ssl ? 'ssl://' : 'tcp://';
        $target = $transport . $host . ':' . $port;

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($target, $errno, $errstr, self::TIMEOUT_SECONDS);

        if ($socket === false) {
            throw new MailboxClientException("Verbindung zum IMAP-Server fehlgeschlagen: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, self::TIMEOUT_SECONDS);
        $this->socket = $socket;

        // Begrüßungszeile lesen ("* OK ..."), bevor irgendein Tag gesendet wird.
        $this->readLine();

        if ($this->settings->encryption === MailboxEncryption::Starttls) {
            $tag = $this->nextTag();
            $this->write($tag . ' STARTTLS');
            $response = $this->readResponseLines($tag);
            $this->assertOk($response, $tag, 'STARTTLS');

            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new MailboxClientException('STARTTLS-Verhandlung mit dem IMAP-Server ist fehlgeschlagen.');
            }
        }
    }

    private function login(): void
    {
        $tag = $this->nextTag();
        $username = $this->quoteString($this->settings->username ?? '');
        $password = $this->quoteString($this->settings->password ?? '');
        $this->write($tag . ' LOGIN ' . $username . ' ' . $password);
        $response = $this->readResponseLines($tag);
        $this->assertOk($response, $tag, 'LOGIN (Zugangsdaten prüfen)');
    }

    private function select(string $folder): void
    {
        $tag = $this->nextTag();
        $this->write($tag . ' SELECT ' . $this->quoteString($folder));
        $response = $this->readResponseLines($tag);
        $this->assertOk($response, $tag, "SELECT \"{$folder}\"");
    }

    /** @return int[] IMAP-Sequenznummern aller Nachrichten im gewählten Ordner. */
    private function searchAll(): array
    {
        $tag = $this->nextTag();
        $this->write($tag . ' SEARCH ALL');
        $response = $this->readResponseLines($tag);
        $this->assertOk($response, $tag, 'SEARCH');

        foreach ($response as $entry) {
            if ($entry['type'] === 'untagged' && preg_match('/^\*\s+SEARCH(.*)$/i', $entry['text'], $m) === 1) {
                $numbers = array_filter(explode(' ', trim($m[1])), static fn ($v) => $v !== '');

                return array_map('intval', $numbers);
            }
        }

        return [];
    }

    private function fetchRawMessage(int $sequenceNumber): ?string
    {
        $tag = $this->nextTag();
        $this->write($tag . ' FETCH ' . $sequenceNumber . ' (BODY[])');
        $response = $this->readResponseLines($tag);
        $this->assertOk($response, $tag, "FETCH {$sequenceNumber}");

        foreach ($response as $entry) {
            if ($entry['type'] === 'literal-prefix') {
                return $entry['literal'];
            }
        }

        return null;
    }

    private function logout(): void
    {
        $tag = $this->nextTag();
        $this->write($tag . ' LOGOUT');
        // Antwort wird bewusst nicht ausgewertet — beim Schließen der
        // Verbindung direkt danach (siehe close()) ist ein Logout-Fehler
        // ohnehin nicht mehr sinnvoll behebbar.
        $this->readResponseLines($tag);
    }

    private function close(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function nextTag(): string
    {
        $this->tagCounter++;

        return 'A' . $this->tagCounter;
    }

    private function write(string $command): void
    {
        if ($this->socket === null) {
            throw new MailboxClientException('IMAP-Verbindung ist nicht aufgebaut.');
        }

        fwrite($this->socket, $command . "\r\n");
    }

    private function readLine(): string
    {
        if ($this->socket === null) {
            throw new MailboxClientException('IMAP-Verbindung ist nicht aufgebaut.');
        }

        $line = '';
        while (!feof($this->socket)) {
            $chunk = fgets($this->socket, 8192);
            if ($chunk === false) {
                break;
            }
            $line .= $chunk;
            if (str_ends_with($line, "\n")) {
                break;
            }
        }

        if ($line === '') {
            throw new MailboxClientException('IMAP-Server hat die Verbindung unerwartet beendet.');
        }

        return rtrim($line, "\r\n");
    }

    private function readBytes(int $length): string
    {
        if ($this->socket === null) {
            throw new MailboxClientException('IMAP-Verbindung ist nicht aufgebaut.');
        }

        $data = '';
        $remaining = $length;

        while ($remaining > 0 && !feof($this->socket)) {
            $chunk = fread($this->socket, min(8192, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }

        if (strlen($data) !== $length) {
            throw new MailboxClientException('IMAP-Literal konnte nicht vollständig gelesen werden (Verbindung abgebrochen?).');
        }

        return $data;
    }

    /**
     * Liest die Serverantwort für den übergebenen Tag ein — eine
     * gemischte Folge aus untagged Zeilen ("* ..."), ggf. eingebetteten
     * Byte-Literalen ({n}<n Bytes>) und der abschließenden getaggten
     * Statuszeile ("A3 OK ..."/"A3 NO ..."/"A3 BAD ..."). Ein Literal wird
     * NICHT zeilenweise gelesen (sein Inhalt kann beliebige Bytes inkl.
     * CRLF enthalten), sondern exakt byte-genau über readBytes().
     *
     * @return array<int, array{type: string, text: string, literal?: string}>
     */
    private function readResponseLines(string $tag): array
    {
        $entries = [];
        $guard = 0;

        while (true) {
            $guard++;
            if ($guard > 100000) {
                throw new MailboxClientException('IMAP-Antwort überschreitet die erwartete Zeilenanzahl (Protokollfehler?).');
            }

            $line = $this->readLine();

            if (preg_match('/\{(\d+)\}\s*$/', $line, $m) === 1) {
                $literalLength = (int) $m[1];
                $literal = $this->readBytes($literalLength);
                $entries[] = ['type' => 'literal-prefix', 'text' => $line, 'literal' => $literal];

                // Nach dem Literal folgt auf derselben logischen Zeile meist
                // noch ein Rest (z. B. die schließende Klammer) — als
                // eigene, normale Zeile weiterlesen (nicht Teil des Literals).
                continue;
            }

            if (preg_match('/^' . preg_quote($tag, '/') . '\s/i', $line) === 1) {
                $entries[] = ['type' => 'tagged', 'text' => $line];

                break;
            }

            $entries[] = ['type' => 'untagged', 'text' => $line];
        }

        return $entries;
    }

    /** @param array<int, array{type: string, text: string, literal?: string}> $response */
    private function assertOk(array $response, string $tag, string $context): void
    {
        foreach ($response as $entry) {
            if ($entry['type'] === 'tagged') {
                if (preg_match('/^' . preg_quote($tag, '/') . '\s+OK/i', $entry['text']) === 1) {
                    return;
                }

                throw new MailboxClientException("IMAP-Befehl fehlgeschlagen ({$context}): " . $entry['text']);
            }
        }

        throw new MailboxClientException("IMAP-Server hat keine getaggte Antwort auf \"{$context}\" gesendet.");
    }

    /** IMAP-Quoted-String (RFC 3501 §4.3): Backslash und Anführungszeichen escapen, in doppelte Anführungszeichen einfassen. */
    private function quoteString(string $value): string
    {
        $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

        return '"' . $escaped . '"';
    }
}
