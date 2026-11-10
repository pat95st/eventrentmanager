<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

use DateTimeImmutable;

/**
 * Kurzlebiger 6-stelliger Code, mit dem sich ein Abspielgerät (AutoEventPlay
 * auf dem Raspberry Pi) selbst an eine Veranstaltung koppelt (Migration 141).
 * Der Code wird in der Veranstaltung erzeugt, am Gerät eingegeben und ist nur
 * einmal und nur bis zum Ablauf gültig.
 */
final class AudioPairingCode
{
    /** Gültigkeit eines neu erzeugten Codes. */
    public const VALID_MINUTES = 15;

    /** Fehlversuche je IP innerhalb von VALID_MINUTES, danach wird gesperrt. */
    public const MAX_FAILURES = 10;

    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly string $code,
        public readonly string $createdByName,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $usedAt = null,
        public ?string $playerId = null,
    ) {
    }

    public function isUsable(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        return $this->usedAt === null && $this->expiresAt > $now;
    }

    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /** Eingabe normalisieren: Leerzeichen/Bindestriche entfernen ("123 456" → "123456"). */
    public static function normalize(string $input): string
    {
        return (string) preg_replace('/[\s\-]+/', '', $input);
    }

    /** Anzeige mit Leerzeichen in der Mitte ("123 456"). */
    public function formatted(): string
    {
        return substr($this->code, 0, 3) . ' ' . substr($this->code, 3);
    }
}
