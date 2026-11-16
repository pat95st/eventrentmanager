<?php

declare(strict_types=1);

namespace App\Application\Display\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Display\DisplayClient;
use App\Domain\Display\DisplayClientRepositoryInterface;
use App\Domain\Display\DisplayOptions;
use App\Domain\Display\DisplayRole;
use App\Domain\Display\DisplaySettingsRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Settings › Stage & FOH displays (migration 167): manage display devices
 * (SaveDisplayClient, RegenerateDisplayClientToken, DeleteDisplayClient)
 * and the global display settings (SaveDisplaySettings).
 */
final class DisplayAdminHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly DisplayClientRepositoryInterface $clients,
        private readonly DisplaySettingsRepositoryInterface $settings,
        private readonly RentalRepositoryInterface $rentals,
        private readonly MinuteProgramRepositoryInterface $programs,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof SaveDisplayClient => $this->save($command),
            $command instanceof RegenerateDisplayClientToken => $this->regenerate($command),
            $command instanceof DeleteDisplayClient => $this->delete($command),
            $command instanceof SaveDisplaySettings => $this->saveSettings($command),
            default => throw new \LogicException('Unknown command ' . $command::class),
        };
    }

    /** @return array{id: string, token: ?string} token only on creation (show once) */
    private function save(SaveDisplayClient $command): array
    {
        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für das Display angeben.']);
        }
        $role = DisplayRole::tryFrom($command->role) ?? throw new ValidationException(['role' => 'Bitte Stagedisplay oder FOH-Display wählen.']);
        [$rentalId, $programId] = $this->target($command->target);
        $room = trim((string) $command->room);
        $room = $room !== '' ? mb_substr($room, 0, 120) : null;

        if ($command->id !== null && $command->id !== '') {
            $client = $this->clients->findById($command->id) ?? throw new NotFoundException('Display nicht gefunden.');
            $client->name = mb_substr($name, 0, 120);
            $client->role = $role;
            $client->enabled = $command->enabled;
            $client->rentalId = $rentalId;
            $client->minuteProgramId = $programId;
            $client->room = $room;
            if ($command->options !== null) {
                $client->options = DisplayOptions::fromArray($command->options, $role);
            }
            $this->clients->save($client);

            return ['id' => $client->id, 'token' => null];
        }

        [$token, $hash, $hint] = DisplayClient::generateToken();
        $client = new DisplayClient(
            id: Uuid::v4(),
            name: mb_substr($name, 0, 120),
            role: $role,
            tokenHash: $hash,
            tokenHint: $hint,
            enabled: $command->enabled,
            rentalId: $rentalId,
            minuteProgramId: $programId,
            room: $room,
            options: $command->options !== null ? DisplayOptions::fromArray($command->options, $role) : DisplayOptions::defaultsFor($role),
            lastSeenAt: null,
            lastIp: null,
            clientVersion: null,
            createdAt: new DateTimeImmutable(),
        );
        $this->clients->save($client);

        return ['id' => $client->id, 'token' => $token];
    }

    /** @return array{0: ?string, 1: ?string} [rental id, minute program id] */
    private function target(string $target): array
    {
        $target = trim($target);
        if ($target === '' || $target === 'auto') {
            return [null, null];
        }
        if (str_starts_with($target, 'rental:')) {
            $rental = $this->rentals->findById(substr($target, 7));
            if ($rental === null) {
                throw new ValidationException(['target' => 'Die Veranstaltung wurde nicht gefunden.']);
            }

            return [$rental->id, null];
        }
        if (str_starts_with($target, 'program:')) {
            $program = $this->programs->findById(substr($target, 8));
            if ($program === null) {
                throw new ValidationException(['target' => 'Das Minutenprogramm wurde nicht gefunden.']);
            }

            return [$program->rentalId, $program->id];
        }

        throw new ValidationException(['target' => 'Ungültige Zuordnung.']);
    }

    private function regenerate(RegenerateDisplayClientToken $command): string
    {
        $client = $this->clients->findById($command->id) ?? throw new NotFoundException('Display nicht gefunden.');
        [$token, $hash, $hint] = DisplayClient::generateToken();
        $client->tokenHash = $hash;
        $client->tokenHint = $hint;
        $this->clients->save($client);

        return $token;
    }

    private function delete(DeleteDisplayClient $command): null
    {
        $this->clients->delete($command->id);

        return null;
    }

    private function saveSettings(SaveDisplaySettings $command): null
    {
        $settings = $this->settings->get();
        $settings->pollIntervalSeconds = max(1, min(60, $command->pollIntervalSeconds));
        $settings->warnSeconds = max(0, min(3600, $command->warnSeconds));
        $settings->criticalSeconds = max(0, min(3600, $command->criticalSeconds));
        if ($settings->criticalSeconds > $settings->warnSeconds) {
            throw new ValidationException(['critical_seconds' => 'Die Schwelle „kritisch“ darf nicht größer als die Warnschwelle sein.']);
        }
        $this->settings->save($settings);

        return null;
    }
}
