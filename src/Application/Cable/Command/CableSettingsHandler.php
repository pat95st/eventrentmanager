<?php

declare(strict_types=1);

namespace App\Application\Cable\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Article\ProtectionClass;
use App\Domain\Cable\CableRepositoryInterface;
use App\Domain\Cable\CableType;
use App\Domain\Cable\ConnectorType;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;

/**
 * Einstellungen › Leitungen (Migration 125): Leitungs- und Steckverbinder-
 * typen pflegen. Ein Handler für die vier zusammengehörigen Commands;
 * Typen, die von Artikeln verwendet werden, lassen sich nicht löschen.
 */
final class CableSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CableRepositoryInterface $cables)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof SaveCableType => $this->saveType($command),
            $command instanceof SaveConnectorType => $this->saveConnector($command),
            $command instanceof DeleteCableType => $this->deleteType($command->id),
            $command instanceof DeleteConnectorType => $this->deleteConnector($command->id),
            default => throw new \LogicException('Unbekannter Befehl.'),
        };
    }

    private function saveType(SaveCableType $command): string
    {
        $existing = $command->id !== null ? $this->cables->findTypeById($command->id) : null;
        if ($command->id !== null && $existing === null) {
            throw new NotFoundException('Leitungstyp wurde nicht gefunden.');
        }
        $name = trim($command->name);
        $errors = [];
        if ($name === '' || mb_strlen($name) > 150) {
            $errors['name'] = 'Bitte eine Bezeichnung angeben (max. 150 Zeichen), z. B. „H07RN-F 5G6“.';
        } elseif ($this->cables->typeNameExists($name, $existing?->id)) {
            $errors['name'] = 'Diesen Leitungstyp gibt es bereits.';
        }
        $protectionClass = null;
        if ($command->requiresElectricalInspection && ($command->protectionClass ?? '') !== '') {
            $protectionClass = ProtectionClass::tryFrom((string) $command->protectionClass);
            if ($protectionClass === null) {
                $errors['protection_class'] = 'Bitte eine gültige Schutzklasse wählen.';
            }
        }
        $known = array_map(static fn (ConnectorType $c) => $c->id, $this->cables->findAllConnectors());
        $connectorIds = array_values(array_intersect(array_unique(array_map('strval', $command->connectorIds)), $known));
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        $description = $command->description !== null && trim($command->description) !== '' ? mb_substr(trim($command->description), 0, 500) : null;

        $type = new CableType(
            id: $existing?->id ?? Uuid::v4(),
            name: $name,
            description: $description,
            requiresElectricalInspection: $command->requiresElectricalInspection,
            protectionClass: $protectionClass,
            connectorIds: $connectorIds,
            sortOrder: self::sort($command->sortOrder, $existing?->sortOrder),
        );
        $this->cables->saveType($type);

        return $type->id;
    }

    private function saveConnector(SaveConnectorType $command): string
    {
        $existing = $command->id !== null ? $this->cables->findConnectorById($command->id) : null;
        if ($command->id !== null && $existing === null) {
            throw new NotFoundException('Steckverbindertyp wurde nicht gefunden.');
        }
        $name = trim($command->name);
        if ($name === '' || mb_strlen($name) > 150) {
            throw new ValidationException(['connector_name' => 'Bitte eine Bezeichnung angeben (max. 150 Zeichen), z. B. „CEE 32 A Stecker rot“.']);
        }
        if ($this->cables->connectorNameExists($name, $existing?->id)) {
            throw new ValidationException(['connector_name' => 'Diesen Steckverbindertyp gibt es bereits.']);
        }
        $connector = new ConnectorType($existing?->id ?? Uuid::v4(), $name, self::sort($command->sortOrder, $existing?->sortOrder));
        $this->cables->saveConnector($connector);

        foreach ($command->allowForCableTypeIds as $typeId) {
            $type = $this->cables->findTypeById((string) $typeId);
            if ($type !== null && !$type->allowsConnector($connector->id)) {
                $type->connectorIds[] = $connector->id;
                $this->cables->saveType($type);
            }
        }

        return $connector->id;
    }

    private function deleteType(string $id): mixed
    {
        $type = $this->cables->findTypeById($id) ?? throw new NotFoundException('Leitungstyp wurde nicht gefunden.');
        $used = $this->cables->usageCounts()[$id] ?? 0;
        if ($used > 0) {
            throw new ConflictException("Der Leitungstyp „{$type->name}“ wird von {$used} Artikel(n) verwendet und kann nicht gelöscht werden.");
        }
        $this->cables->deleteType($id);

        return null;
    }

    private function deleteConnector(string $id): mixed
    {
        $connector = $this->cables->findConnectorById($id) ?? throw new NotFoundException('Steckverbindertyp wurde nicht gefunden.');
        $used = $this->cables->usageCounts()[$id] ?? 0;
        if ($used > 0) {
            throw new ConflictException("Der Steckverbinder „{$connector->name}“ wird von {$used} Artikel(n) verwendet und kann nicht gelöscht werden.");
        }
        $this->cables->deleteConnector($id);

        return null;
    }

    private static function sort(?string $raw, ?int $fallback): int
    {
        $raw = $raw !== null ? trim($raw) : '';

        return $raw !== '' && preg_match('/^-?\d{1,6}$/', $raw) === 1 ? (int) $raw : ($fallback ?? 0);
    }
}
