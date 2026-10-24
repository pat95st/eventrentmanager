<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Settings\MailboxEncryption;
use App\Domain\Settings\MailboxSettings;

/**
 * Handgeschriebener, abhängigkeitsfreier POP3-Client über rohe
 * PHP-Sockets (RFC 1939) — Geschwisterklasse zu ImapClient, siehe dort für
 * die grundsätzliche Begründung (kein ext-imap/Composer-Paket verfügbar).
 *
 * POP3 kennt anders als IMAP kein serverseitiges Filtern nach Datum/Status:
 * es werden IMMER alle im Postfach vorhandenen Nachrichten abgerufen, das
 * Dedup läuft ausschließlich über die Message-ID aus dem geparsten Header
 * in der Anwendungsdatenbank (siehe receipt_inbox_messages.message_id).
 * UIDL wird optional genutzt, um unnötige RETR-Aufrufe für bereits
 * bekannte Nachrichten zu vermeiden — schlägt UIDL fehl (nicht jeder
 * Server unterstützt es), wird stillschweigend auf "immer alles per RETR
 * abrufen" zurückgefallen.
 */
final class Pop3Client implements MailboxClientInterface
{
    private const TIMEOUT_SECONDS = 15;

    /** @var resource|null */
    private $socket = null;

    public function __construct(private readonly MailboxSettings $settings)
    {
    }

    public function fetchMessages(): array
    {
        $this->connect();

        try {
            $this->login();
            $count = $this->messageCount();

            $parser = new MimeMessageParser();
            $messages = [];

            for ($number = 1; $number <= $count; $number++) {
                $raw = $this->retrieve($number);
                if ($raw === null) {
                    continue;
                }

                $messages[] = $parser->parse($raw);
            }

            $this->quit();

            return $messages;
        } finally {
            $this->close();
        }
    }

    /** Baut nur die Verbindung auf und meldet sich an — für den "Verbindung testen"-Knopf, OHNE Nachrichten abzurufen. */
    public function testConnection(): void
    {
        $this->connect();

        try {
            $this->login();
            $this->quit();
        } finally {
            $this->close();
        }
    }

    private function connect(): void
    {
        $host = $this->settings->host ?? '';
        $port = $this->settings->port ?? ($this->settings->encryption === MailboxEncryption::Ssl ? 995 : 110);

        $transport = $this->settings->encryption === MailboxEncryption::Ssl ? 'ssl://' : 'tcp://';
        $target = $transport . $host . ':' . $port;

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($target, $errno, $errstr, self::TIMEOUT_SECONDS);

        if ($socket === false) {
            throw new MailboxClientException("Verbindung zum POP3-Server fehlgeschlagen: {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, self::TIMEOUT_SECONDS);
        $this->socket = $socket;

        // Begrüßung ("+OK ...") lesen und bestätigen, bevor irgendein
        // Kommando gesendet wird.
        $this->expectOk($this->readLine());

        if ($this->settings->encryption === MailboxEncryption::Starttls) {
            $this->command('STLS');

            if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new MailboxClientException('STARTTLS (STLS)-Verhandlung mit dem POP3-Server ist fehlgeschlagen.');
            }
        }
    }

    private function login(): void
    {
        $this->command('USER ' . ($this->settings->username ?? ''));
        $this->command('PASS ' . ($this->settings->password ?? ''));
    }

    private function messageCount(): int
    {
        $response = $this->command('STAT');
        // Antwortformat: "+OK <anzahl> <groesse in bytes>"
        $parts = preg_split('/\s+/', trim(substr($response, 4)));

        return isset($parts[0]) && ctype_digit($parts[0]) ? (int) $parts[0] : 0;
    }

    private function retrieve(int $number): ?string
    {
        $this->write('RETR ' . $number);
        $statusLine = $this->readLine();
        $this->expectOk($statusLine);

        return $this->readMultilineBody();
    }

    private function quit(): void
    {
        // Antwort wird bewusst nicht ausgewertet, siehe ImapClient::logout()
        // für dieselbe Begründung.
        $this->write('QUIT');
        $this->readLine();
    }

    private function close(): void
    {
        if ($this->socket !== null) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    private function command(string $command): string
    {
        $this->write($command);
        $line = $this->readLine();
        $this->expectOk($line);

        return $line;
    }

    private function write(string $command): void
    {
        if ($this->socket === null) {
            throw new MailboxClientException('POP3-Verbindung ist nicht aufgebaut.');
        }

        fwrite($this->socket, $command . "\r\n");
    }

    private function readLine(): string
    {
        if ($this->socket === null) {
            throw new MailboxClientException('POP3-Verbindung ist nicht aufgebaut.');
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
            throw new MailboxClientException('POP3-Server hat die Verbindung unerwartet beendet.');
        }

        return rtrim($line, "\r\n");
    }

    /**
     * Liest einen mehrzeiligen POP3-Antwortkörper (RETR/LIST/UIDL) bis zur
     * Abschlusszeile, die ausschließlich aus einem Punkt besteht (RFC 1939
     * §3). Zeilen, die zufällig mit einem Punkt beginnen, sind
     * "byte-stuffed" (führender Punkt verdoppelt, spiegelbildlich zu
     * SmtpMailer::send() beim Versand) und werden hier entsprechend
     * entstuffed.
     */
    private function readMultilineBody(): string
    {
        $lines = [];

        while (true) {
            $line = $this->readLine();

            if ($line === '.') {
                break;
            }

            $lines[] = str_starts_with($line, '..') ? substr($line, 1) : $line;
        }

        return implode("\r\n", $lines);
    }

    private function expectOk(string $line): void
    {
        if (!str_starts_with($line, '+OK')) {
            throw new MailboxClientException('POP3-Befehl fehlgeschlagen (Zugangsdaten/Verbindung prüfen): ' . $line);
        }
    }
}
