<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Api\RateLimit;

use App\Domain\Settings\ApiSettings;

/**
 * Minimal Redis client speaking RESP2 over a stream socket, so no PHP
 * extension (phpredis) is required. Supports TCP, TLS and Unix domain
 * sockets, AUTH (password or ACL user + password) and SELECT. Only what the
 * rate limiter needs: sending a command and reading one reply.
 *
 * Every failure (connect, timeout, protocol, "-ERR" reply) is reported as
 * RateLimiterUnavailable so callers can fall back to the database.
 */
final class RedisClient
{
    /** @var resource|null */
    private $stream = null;

    public function __construct(
        private readonly string $address,
        private readonly float $timeoutSeconds = 1.0,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private readonly int $database = 0,
    ) {
    }

    public static function fromSettings(ApiSettings $settings): self
    {
        return new self(
            self::address($settings),
            $settings->redisTimeoutSeconds,
            $settings->redisUsername,
            $settings->redisPassword,
            $settings->redisDatabase,
        );
    }

    /** Stream address for stream_socket_client(), e.g. "tcp://127.0.0.1:6379" or "unix:///run/redis/redis.sock". */
    public static function address(ApiSettings $settings): string
    {
        if ($settings->redisConnection === ApiSettings::REDIS_SOCKET) {
            return 'unix://' . (string) $settings->redisSocketPath;
        }
        $host = $settings->redisHost;
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $host = '[' . $host . ']'; // IPv6 literal
        }
        $scheme = $settings->redisConnection === ApiSettings::REDIS_TLS ? 'tls' : 'tcp';

        return $scheme . '://' . $host . ':' . $settings->redisPort;
    }

    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /** Sends a command and returns the decoded reply (string, int, null or array). */
    public function command(string ...$parts): mixed
    {
        $this->connect();

        return $this->send($parts);
    }

    /** Connection test for the settings page: returns the server version (or "unknown"). */
    public function ping(): string
    {
        $reply = $this->command('PING');
        if ($reply !== 'PONG') {
            throw new RateLimiterUnavailable('Unerwartete Antwort auf PING.');
        }
        $info = $this->command('INFO', 'server');
        if (is_string($info) && preg_match('/^redis_version:(\S+)/m', $info, $m) === 1) {
            return $m[1];
        }

        return 'unbekannt';
    }

    private function connect(): void
    {
        if (is_resource($this->stream)) {
            return;
        }

        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client($this->address, $errno, $errstr, $this->timeoutSeconds, STREAM_CLIENT_CONNECT);
        if ($stream === false) {
            throw new RateLimiterUnavailable(sprintf('Verbindung zu Redis (%s) fehlgeschlagen: %s', $this->address, $errstr !== '' ? $errstr : 'Fehler ' . $errno));
        }
        $seconds = (int) floor($this->timeoutSeconds);
        stream_set_timeout($stream, $seconds, (int) (($this->timeoutSeconds - $seconds) * 1_000_000));
        $this->stream = $stream;

        if ($this->password !== null && $this->password !== '') {
            $this->send($this->username !== null && $this->username !== '' ? ['AUTH', $this->username, $this->password] : ['AUTH', $this->password]);
        }
        if ($this->database > 0) {
            $this->send(['SELECT', (string) $this->database]);
        }
    }

    /** @param list<string> $parts */
    private function send(array $parts): mixed
    {
        $payload = '*' . count($parts) . "\r\n";
        foreach ($parts as $part) {
            $payload .= '$' . strlen($part) . "\r\n" . $part . "\r\n";
        }
        $this->write($payload);

        return $this->readReply();
    }

    private function write(string $payload): void
    {
        $remaining = $payload;
        while ($remaining !== '') {
            $written = @fwrite($this->stream, $remaining);
            if ($written === false || $written === 0) {
                $this->fail('Schreiben an Redis fehlgeschlagen.');
            }
            $remaining = substr($remaining, $written);
        }
    }

    private function readReply(): mixed
    {
        $line = $this->readLine();
        $type = $line[0] ?? '';
        $rest = substr($line, 1);

        return match ($type) {
            '+' => $rest,
            '-' => $this->fail('Redis meldet: ' . $rest),
            ':' => (int) $rest,
            '$' => $this->readBulk((int) $rest),
            '*' => $this->readArray((int) $rest),
            default => $this->fail('Ungültige Antwort von Redis.'),
        };
    }

    private function readBulk(int $length): ?string
    {
        if ($length < 0) {
            return null;
        }
        $data = '';
        while (strlen($data) < $length + 2) {
            $chunk = @fread($this->stream, $length + 2 - strlen($data));
            if ($chunk === false || $chunk === '') {
                $this->fail('Zeitüberschreitung beim Lesen von Redis.');
            }
            $data .= $chunk;
        }

        return substr($data, 0, $length);
    }

    /** @return list<mixed>|null */
    private function readArray(int $count): ?array
    {
        if ($count < 0) {
            return null;
        }
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $this->readReply();
        }

        return $items;
    }

    private function readLine(): string
    {
        $line = @fgets($this->stream);
        if ($line === false) {
            $meta = is_resource($this->stream) ? stream_get_meta_data($this->stream) : ['timed_out' => false];
            $this->fail(!empty($meta['timed_out']) ? 'Zeitüberschreitung beim Lesen von Redis.' : 'Verbindung zu Redis unterbrochen.');
        }

        return rtrim($line, "\r\n");
    }

    private function fail(string $message): never
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;

        throw new RateLimiterUnavailable($message);
    }
}
