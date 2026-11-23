<?php

declare(strict_types=1);

namespace App\Domain\Logbook;

use DateTimeImmutable;

/** Audit trail entry of a trip (migration 184). */
final class LogbookEntryChange
{
    public const CREATED = 'created';
    public const UPDATED = 'updated';
    public const DELETED = 'deleted';
    public const RESTORED = 'restored';

    /** @param array<string, array{0: mixed, 1: mixed}> $changes field => [old, new] */
    public function __construct(
        public readonly string $id,
        public readonly string $entryId,
        public readonly ?string $userId,
        public readonly ?string $actorName,
        public readonly string $action,
        public readonly array $changes,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /** @return array<string, array{0: mixed, 1: mixed}> */
    public static function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $field => $value) {
            $old = $before[$field] ?? null;
            if ($old !== $value) {
                $changes[$field] = [$old, $value];
            }
        }

        return $changes;
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::CREATED => 'angelegt',
            self::UPDATED => 'geändert',
            self::DELETED => 'gelöscht',
            self::RESTORED => 'wiederhergestellt',
            default => $this->action,
        };
    }
}
