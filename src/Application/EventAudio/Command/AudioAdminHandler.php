<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\EventAudio\AudioFormat;
use App\Domain\EventAudio\AudioPlayer;
use App\Domain\EventAudio\AudioPlayerRepositoryInterface;
use App\Domain\EventAudio\AudioSettings;
use App\Domain\EventAudio\AudioSettingsRepositoryInterface;
use App\Domain\EventAudio\RentalAudioSettings;
use App\Domain\EventAudio\RentalAudioSettingsRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Verwaltung: Abspielgeräte (SaveAudioPlayer, RegenerateAudioPlayerToken,
 * DeleteAudioPlayer), globale Einstellungen (SaveAudioSettings, UploadGong,
 * ResetGong) und Zuordnung je Veranstaltung (SaveRentalAudioSettings).
 */
final class AudioAdminHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly AudioPlayerRepositoryInterface $players,
        private readonly AudioSettingsRepositoryInterface $settings,
        private readonly RentalAudioSettingsRepositoryInterface $rentalSettings,
        private readonly LocalFileStorage $storage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        return match (true) {
            $command instanceof SaveAudioPlayer => $this->savePlayer($command),
            $command instanceof RegenerateAudioPlayerToken => $this->regenerate($command),
            $command instanceof DeleteAudioPlayer => $this->deletePlayer($command),
            $command instanceof SaveAudioSettings => $this->saveSettings($command),
            $command instanceof UploadGong => $this->uploadGong($command),
            $command instanceof ResetGong => $this->resetGong(),
            $command instanceof SaveRentalAudioSettings => $this->saveRental($command),
            default => throw new \LogicException('Unbekannter Befehl ' . $command::class),
        };
    }

    /** @return array{id: string, token: ?string} Token nur beim Anlegen (einmalig anzeigen). */
    private function savePlayer(SaveAudioPlayer $command): array
    {
        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für das Abspielgerät angeben.']);
        }
        if ($command->id !== null && $command->id !== '') {
            $player = $this->players->findById($command->id) ?? throw new NotFoundException('Abspielgerät nicht gefunden.');
            $player->name = mb_substr($name, 0, 120);
            $player->enabled = $command->enabled;
            $this->players->save($player);

            return ['id' => $player->id, 'token' => null];
        }
        [$token, $hash, $hint] = AudioPlayer::generateToken();
        $player = new AudioPlayer(Uuid::v4(), mb_substr($name, 0, 120), $hash, $hint, true, null, null, null, null, new DateTimeImmutable());
        $this->players->save($player);

        return ['id' => $player->id, 'token' => $token];
    }

    private function regenerate(RegenerateAudioPlayerToken $command): string
    {
        $player = $this->players->findById($command->id) ?? throw new NotFoundException('Abspielgerät nicht gefunden.');
        [$token, $hash, $hint] = AudioPlayer::generateToken();
        $player->tokenHash = $hash;
        $player->tokenHint = $hint;
        $this->players->save($player);

        return $token;
    }

    private function deletePlayer(DeleteAudioPlayer $command): null
    {
        $this->players->delete($command->id);

        return null;
    }

    private function saveSettings(SaveAudioSettings $command): null
    {
        $settings = $this->settings->get();
        $language = trim($command->ttsLanguage);
        if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $language)) {
            throw new ValidationException(['tts_language' => 'Sprache bitte als Code angeben, z. B. de-DE.']);
        }
        $settings->ttsLanguage = $language;
        $voice = trim((string) $command->ttsVoice);
        $settings->ttsVoice = $voice !== '' ? mb_substr($voice, 0, 100) : null;
        $settings->ttsRate = max(0.5, min(2.0, round($command->ttsRate, 2)));
        $settings->maxUploadMb = max(1, min(500, $command->maxUploadMb));
        $settings->queueExpiryMinutes = max(1, min(240, $command->queueExpiryMinutes));
        $this->settings->save($settings);

        return null;
    }

    private function uploadGong(UploadGong $command): null
    {
        $file = $command->uploadedFile;
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['gong' => 'Bitte eine Audio-Datei auswählen.']);
        }
        $name = basename((string) $file['name']);
        $mime = AudioFormat::uploadMimeFor($name);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($mime === null || !AudioFormat::looksLikeAudio((string) $file['tmp_name'], $ext)) {
            throw new ValidationException(['gong' => 'Erlaubt sind WAV, FLAC, AAC (.m4a) und MP3.']);
        }
        if ((int) $file['size'] > 10 * 1024 * 1024) {
            throw new ValidationException(['gong' => 'Der Gong darf höchstens 10 MB groß sein.']);
        }
        $settings = $this->settings->get();
        $old = $settings->gongStoredFilename;
        $stored = $this->storage->storeUploadedFile($file, AudioSettings::GONG_DIRECTORY, 'gong-' . bin2hex(random_bytes(4)));
        $settings->gongOriginalFilename = $stored['originalFilename'];
        $settings->gongStoredFilename = $stored['storedFilename'];
        $settings->gongMimeType = $mime;
        $this->settings->save($settings);
        if ($old !== null) {
            try {
                $this->storage->delete(AudioSettings::GONG_DIRECTORY, $old);
            } catch (\Throwable) {
            }
        }

        return null;
    }

    private function resetGong(): null
    {
        $settings = $this->settings->get();
        if ($settings->gongStoredFilename !== null) {
            try {
                $this->storage->delete(AudioSettings::GONG_DIRECTORY, $settings->gongStoredFilename);
            } catch (\Throwable) {
            }
        }
        $settings->gongOriginalFilename = null;
        $settings->gongStoredFilename = null;
        $settings->gongMimeType = null;
        $this->settings->save($settings);

        return null;
    }

    private function saveRental(SaveRentalAudioSettings $command): null
    {
        $playerId = $command->playerId !== null && $command->playerId !== '' ? $command->playerId : null;
        if ($playerId !== null && $this->players->findById($playerId) === null) {
            throw new ValidationException(['player' => 'Abspielgerät nicht gefunden.']);
        }
        $this->rentalSettings->save(new RentalAudioSettings($command->rentalId, $playerId, $command->gongByDefault));

        return null;
    }
}
