<?php

declare(strict_types=1);

namespace App\Domain\Display;

use DateTimeImmutable;

/**
 * Display device (stage or FOH display, migration 167) that authenticates
 * with a device token at the display API (/api/display/v1, see
 * docs/display-api.md).
 *
 * Which minute program it shows: the pinned $minuteProgramId if set,
 * otherwise the current program of $rentalId – or, without an event, of all
 * events running today – optionally limited to $room. See
 * App\Application\Display\DisplayProgramResolver.
 */
final class DisplayClient
{
    /** Counts as online if the device polled within this time. */
    public const ONLINE_SECONDS = 45;

    public function __construct(
        public readonly string $id,
        public string $name,
        public DisplayRole $role,
        public string $tokenHash,
        public string $tokenHint,
        public bool $enabled,
        public ?string $rentalId,
        public ?string $minuteProgramId,
        public ?string $room,
        public DisplayOptions $options,
        public ?DateTimeImmutable $lastSeenAt,
        public ?string $lastIp,
        public ?string $clientVersion,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    public function isOnline(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        return $this->enabled && $this->lastSeenAt !== null && ($now->getTimestamp() - $this->lastSeenAt->getTimestamp()) <= self::ONLINE_SECONDS;
    }

    /** Control (delay, start, finish, reset) is only allowed for FOH displays with the option enabled. */
    public function canControl(): bool
    {
        return $this->role === DisplayRole::Foh && $this->options->allowControl;
    }

    /** @return array{0: string, 1: string, 2: string} [plain token, hash, hint] */
    public static function generateToken(): array
    {
        $token = 'mvd_' . bin2hex(random_bytes(24));

        return [$token, self::hashToken($token), substr($token, 0, 8) . '…' . substr($token, -4)];
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
