<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\EventAudio\AudioPairingCode;
use App\Domain\EventAudio\AudioPairingCodeRepositoryInterface;
use App\Domain\EventAudio\AudioPlayer;
use App\Domain\EventAudio\AudioPlayerRepositoryInterface;
use App\Domain\EventAudio\RentalAudioSettingsRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Kopplung von Abspielgeräten per 6-stelligem Code (Migration 141):
 * CreateAudioPairingCode (Verwaltung, Veranstaltung › Audio & Durchsagen) und
 * PairAudioPlayer (Player-API, POST /api/player/v1/pair).
 */
final class AudioPairingHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly AudioPairingCodeRepositoryInterface $codes,
        private readonly AudioPlayerRepositoryInterface $players,
        private readonly RentalAudioSettingsRepositoryInterface $rentalSettings,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof CreateAudioPairingCode => $this->create($command),
            $command instanceof PairAudioPlayer => $this->pair($command),
            default => throw new \LogicException('Unbekannter Befehl ' . $command::class),
        };
    }

    private function create(CreateAudioPairingCode $command): AudioPairingCode
    {
        $now = new DateTimeImmutable();
        $this->codes->invalidateForRental($command->rentalId, $now);
        // Kollision mit einem gerade aktiven Code einer anderen Veranstaltung vermeiden
        for ($i = 0; $i < 20; $i++) {
            $value = AudioPairingCode::generateCode();
            if ($this->codes->findUsable($value, $now) === null) {
                break;
            }
        }
        $code = new AudioPairingCode(
            Uuid::v4(),
            $command->rentalId,
            $value,
            mb_substr(trim($command->createdByName) !== '' ? trim($command->createdByName) : 'Verwaltung', 0, 160),
            $now,
            $now->modify('+' . AudioPairingCode::VALID_MINUTES . ' minutes'),
        );
        $this->codes->save($code);

        return $code;
    }

    /**
     * @return array{player: AudioPlayer, token: ?string, rental_id: string}
     *         token ist null, wenn ein bereits gekoppeltes Gerät seinen Token behält.
     */
    private function pair(PairAudioPlayer $command): array
    {
        $now = new DateTimeImmutable();
        $ip = $command->ip ?? 'unbekannt';
        if ($this->codes->countFailuresSince($ip, $now->modify('-' . AudioPairingCode::VALID_MINUTES . ' minutes')) >= AudioPairingCode::MAX_FAILURES) {
            throw new ValidationException(['code' => 'Zu viele Fehlversuche. Bitte in einigen Minuten erneut versuchen.']);
        }
        $value = AudioPairingCode::normalize($command->code);
        $code = preg_match('/^\d{6}$/', $value) === 1 ? $this->codes->findUsable($value, $now) : null;
        if ($code === null) {
            $this->codes->recordFailure($ip, $now);
            throw new ValidationException(['code' => 'Der Code ist ungültig oder abgelaufen.']);
        }

        $player = null;
        $token = null;
        $existing = $command->existingToken !== null ? trim($command->existingToken) : '';
        if ($existing !== '') {
            $found = $this->players->findByTokenHash(hash('sha256', $existing));
            $player = $found !== null && $found->enabled ? $found : null;
        }
        $name = trim((string) $command->deviceName);
        if ($player === null) {
            [$token, $hash, $hint] = AudioPlayer::generateToken();
            $player = new AudioPlayer(
                Uuid::v4(),
                mb_substr($name !== '' ? $name : 'AutoEventPlay', 0, 120),
                $hash,
                $hint,
                true,
                null,
                null,
                null,
                null,
                $now,
            );
        } elseif ($name !== '') {
            $player->name = mb_substr($name, 0, 120);
        }
        $this->players->save($player);

        $settings = clone $this->rentalSettings->get($code->rentalId); // Logo, Sprache u. a. bleiben erhalten
        $settings->playerId = $player->id;
        $this->rentalSettings->save($settings);

        $code->usedAt = $now;
        $code->playerId = $player->id;
        $this->codes->save($code);

        return ['player' => $player, 'token' => $token, 'rental_id' => $code->rentalId];
    }
}
