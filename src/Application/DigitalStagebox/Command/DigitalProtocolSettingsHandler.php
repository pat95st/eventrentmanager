<?php

declare(strict_types=1);

namespace App\Application\DigitalStagebox\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\DigitalStagebox\DigitalProtocol;
use App\Domain\DigitalStagebox\DigitalStageboxRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;

/**
 * Einstellungen › Digitale Protokolle (Migration 152): Protokolle anlegen,
 * umbenennen, löschen. Protokolle, die von digitalen Stageboxen verwendet
 * werden, lassen sich nicht löschen.
 */
final class DigitalProtocolSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly DigitalStageboxRepositoryInterface $stageboxes)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof SaveDigitalProtocol => $this->save($command),
            $command instanceof DeleteDigitalProtocol => $this->delete($command->id),
            default => throw new \LogicException('Unbekannter Befehl.'),
        };
    }

    private function save(SaveDigitalProtocol $command): string
    {
        $existing = $command->id !== null ? $this->stageboxes->findProtocolById($command->id) : null;
        if ($command->id !== null && $existing === null) {
            throw new NotFoundException('Digitales Protokoll wurde nicht gefunden.');
        }
        $name = trim($command->name);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new ValidationException(['name' => 'Bitte eine Bezeichnung angeben (max. 100 Zeichen), z. B. „Dante“.']);
        }
        if ($this->stageboxes->protocolNameExists($name, $existing?->id)) {
            throw new ValidationException(['name' => 'Dieses digitale Protokoll gibt es bereits.']);
        }

        $protocol = new DigitalProtocol($existing?->id ?? Uuid::v4(), $name, self::sort($command->sortOrder, $existing?->sortOrder));
        $this->stageboxes->saveProtocol($protocol);

        return $protocol->id;
    }

    private function delete(string $id): mixed
    {
        $protocol = $this->stageboxes->findProtocolById($id) ?? throw new NotFoundException('Digitales Protokoll wurde nicht gefunden.');
        $used = $this->stageboxes->usageCounts()[$id] ?? 0;
        if ($used > 0) {
            throw new ConflictException("Das Protokoll „{$protocol->name}“ wird von {$used} digitalen Stagebox(en) verwendet und kann nicht gelöscht werden.");
        }
        $this->stageboxes->deleteProtocol($id);

        return null;
    }

    private static function sort(?string $raw, ?int $fallback): int
    {
        $raw = $raw !== null ? trim($raw) : '';

        return $raw !== '' && preg_match('/^-?\d{1,6}$/', $raw) === 1 ? (int) $raw : ($fallback ?? 0);
    }
}
