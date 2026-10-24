<?php

declare(strict_types=1);

namespace App\Infrastructure\Printing;

/**
 * Low-Level-Transport: öffnet eine rohe TCP-Verbindung zu einem
 * Etikettendrucker (Standard-Port 9100, "RAW"/"JetDirect"-Druck, wie bei
 * Brother P-touch/QL-Modellen üblich — kein Vendor-SDK nötig), schreibt die
 * übergebenen Bytes und schließt die Verbindung wieder. Weiß nichts über das
 * Druckerprotokoll selbst (siehe dafür BrotherPtouchRasterDriver) — reine
 * Übertragungsmechanik, analog zur Trennung SimplePdfWriter (Low-Level) vs.
 * PackingListPdfGenerator (fachliche Orchestrierung) bei den PDF-Dokumenten.
 *
 * Nutzt die "sockets"-Erweiterung (in dieser Umgebung vorhanden, siehe
 * `php -m`); Verbindungsaufbau UND das Schreiben selbst sind mit Timeout
 * versehen, damit ein nicht erreichbarer/hängender Drucker die Anwendung
 * nicht blockiert.
 */
final class TcpPrinterTransport
{
    public function __construct(private readonly float $timeoutSeconds = 5.0)
    {
    }

    /** @throws PrinterConnectionException bei jedem Verbindungs-/Übertragungsfehler. */
    public function send(string $ipAddress, int $port, string $bytes): void
    {
        if (!extension_loaded('sockets')) {
            $this->sendViaStream($ipAddress, $port, $bytes);

            return;
        }

        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            throw new PrinterConnectionException('Netzwerkverbindung konnte nicht initialisiert werden: ' . socket_strerror(socket_last_error()));
        }

        // Timeout für Connect UND für Lese-/Schreibvorgänge separat setzen,
        // da socket_connect() selbst kein Timeout-Argument kennt.
        $timeoutSpec = ['sec' => (int) $this->timeoutSeconds, 'usec' => (int) (($this->timeoutSeconds - floor($this->timeoutSeconds)) * 1_000_000)];
        socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, $timeoutSpec);
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, $timeoutSpec);
        socket_set_nonblock($socket);

        $connectResult = @socket_connect($socket, $ipAddress, $port);
        if ($connectResult === false) {
            $errorCode = socket_last_error($socket);
            // EINPROGRESS (115) ist bei nonblocking Sockets der Normalfall —
            // der eigentliche Verbindungsaufbau wird über select() abgewartet.
            if ($errorCode !== 115 && $errorCode !== 36) {
                socket_close($socket);
                throw new PrinterConnectionException($this->friendlyConnectMessage($ipAddress, $port, socket_strerror($errorCode)));
            }

            $write = [$socket];
            $except = [$socket];
            $read = null;
            $selectResult = @socket_select($read, $write, $except, (int) $this->timeoutSeconds, (int) (($this->timeoutSeconds - floor($this->timeoutSeconds)) * 1_000_000));

            if ($selectResult === false || $selectResult === 0 || $except !== []) {
                socket_close($socket);
                throw new PrinterConnectionException("Zeitüberschreitung beim Verbindungsaufbau zu {$ipAddress}:{$port}. Bitte IP-Adresse, Port und Netzwerkverbindung des Druckers prüfen.");
            }
        }

        socket_set_block($socket);

        $totalLength = strlen($bytes);
        $written = 0;
        while ($written < $totalLength) {
            $chunk = @socket_write($socket, substr($bytes, $written), $totalLength - $written);
            if ($chunk === false) {
                $errorCode = socket_last_error($socket);
                socket_close($socket);
                throw new PrinterConnectionException('Fehler beim Senden an den Drucker: ' . socket_strerror($errorCode));
            }
            $written += $chunk;
        }

        socket_close($socket);
    }

    /** Fallback für Umgebungen ohne "sockets"-Erweiterung, über die stream-Funktionen (fsockopen). */
    private function sendViaStream(string $ipAddress, int $port, string $bytes): void
    {
        $errorNumber = 0;
        $errorMessage = '';
        $stream = @fsockopen($ipAddress, $port, $errorNumber, $errorMessage, $this->timeoutSeconds);

        if ($stream === false) {
            throw new PrinterConnectionException($this->friendlyConnectMessage($ipAddress, $port, $errorMessage !== '' ? $errorMessage : 'unbekannter Fehler'));
        }

        stream_set_timeout($stream, (int) $this->timeoutSeconds);

        $totalLength = strlen($bytes);
        $written = 0;
        while ($written < $totalLength) {
            $chunk = fwrite($stream, substr($bytes, $written));
            $meta = stream_get_meta_data($stream);
            if ($chunk === false || $meta['timed_out']) {
                fclose($stream);
                throw new PrinterConnectionException("Zeitüberschreitung beim Senden an {$ipAddress}:{$port}.");
            }
            $written += $chunk;
        }

        fclose($stream);
    }

    private function friendlyConnectMessage(string $ipAddress, int $port, string $detail): string
    {
        return "Verbindung zum Drucker unter {$ipAddress}:{$port} fehlgeschlagen ({$detail}). Bitte prüfen, ob der Drucker eingeschaltet, im Netzwerk erreichbar und die hinterlegte IP-Adresse/Port korrekt ist.";
    }
}
