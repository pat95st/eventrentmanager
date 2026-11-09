<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

use DateTimeImmutable;

/**
 * Abspielgerät (Raspberry Pi am Mischpult), das sich per Geräte-Token an der
 * Player-API meldet (siehe EventAudioPlayerApiController). Ein Gerät wird
 * einer Veranstaltung zugeordnet und spielt dann deren Jingles, Audio-Dateien
 * und Durchsagen ab.
 */
final class AudioPlayer
{
    /** Gilt als online, wenn sich das Gerät in dieser Zeit gemeldet hat. */
    public const ONLINE_SECONDS = 45;

    public function __construct(
        public readonly string $id,
        public string $name,
        public string $tokenHash,
        public string $tokenHint,
        public bool $enabled,
        public ?DateTimeImmutable $lastSeenAt,
        public ?string $lastIp,
        public ?string $playerVersion,
        public ?string $nowPlaying,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    public function isOnline(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        return $this->enabled && $this->lastSeenAt !== null && ($now->getTimestamp() - $this->lastSeenAt->getTimestamp()) <= self::ONLINE_SECONDS;
    }

    /** @return array{0: string, 1: string, 2: string} [Klartext-Token, Hash, Hinweis] */
    public static function generateToken(): array
    {
        $token = 'mvp_' . bin2hex(random_bytes(24));

        return [$token, hash('sha256', $token), substr($token, 0, 8) . '…' . substr($token, -4)];
    }
}
