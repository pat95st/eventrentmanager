<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\EventAudio\Command\DeleteAudioPlayer;
use App\Application\EventAudio\Command\RegenerateAudioPlayerToken;
use App\Application\EventAudio\Command\ResetGong;
use App\Application\EventAudio\Command\SaveAudioPlayer;
use App\Application\EventAudio\Command\SaveAudioSettings;
use App\Application\EventAudio\Command\UploadGong;
use App\Domain\EventAudio\AudioPlayerRepositoryInterface;
use App\Domain\EventAudio\AudioSettings;
use App\Domain\EventAudio\AudioSettingsRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Einstellungen › Lager & Veranstaltungen › Veranstaltungs-Audio (Migration 135):
 * Gong vor Durchsagen, Sprachausgabe, Upload-Limit und Abspielgeräte
 * (Raspberry Pi) mit Geräte-Token.
 */
final class AudioSettingsController
{
    private const URL = '/einstellungen/veranstaltungs-audio';
    private const SESSION_TOKEN = 'audio_player_new_token';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly AudioSettingsRepositoryInterface $settings,
        private readonly AudioPlayerRepositoryInterface $players,
        private readonly LocalFileStorage $storage,
        private readonly ?\App\Domain\Settings\AiAdImageSettingsRepositoryInterface $aiSettings = null,
    ) {
    }

    public function index(Request $request): void
    {
        Session::ensureStarted();
        $newToken = $_SESSION[self::SESSION_TOKEN] ?? null;
        unset($_SESSION[self::SESSION_TOKEN]);
        $scheme = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';

        echo View::page('settings/event-audio', [
            'settings' => $this->settings->get(),
            'players' => $this->players->findAll(),
            'newToken' => is_array($newToken) ? $newToken : null,
            'openaiConfigured' => $this->aiSettings?->get()->isConfigured() ?? false,
            'apiBaseUrl' => $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/api/player/v1',
        ], activeNav: 'settings-veranstaltungs-audio', title: 'Veranstaltungs-Audio');
    }

    public function saveSettings(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new SaveAudioSettings(
            $request->input('tts_language', 'de-DE') ?? 'de-DE',
            $request->input('tts_voice'),
            (float) str_replace(',', '.', $request->input('tts_rate', '1') ?? '1'),
            (int) ($request->input('max_upload_mb', '50') ?? 50),
            (int) ($request->input('queue_expiry_minutes', '10') ?? 10),
            $request->input('translator_provider', 'none'),
            $request->input('deepl_api_key'),
            $request->input('openai_translation_model'),
            $request->input('remove_deepl_api_key') === '1',
        )), 'Die Einstellungen wurden gespeichert.');
    }

    public function uploadGong(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new UploadGong($request->file('gong') ?? ['error' => UPLOAD_ERR_NO_FILE])), 'Der Gong wurde hochgeladen.');
    }

    public function resetGong(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new ResetGong()), 'Der eigene Gong wurde entfernt – das Abspielgerät nutzt wieder seinen eingebauten Gong.');
    }

    public function gong(Request $request): void
    {
        $settings = $this->settings->get();
        if ($settings->gongStoredFilename === null) {
            Response::notFound('Es ist kein eigener Gong hinterlegt.');

            return;
        }
        Response::file($this->storage->absolutePath(AudioSettings::GONG_DIRECTORY, $settings->gongStoredFilename), (string) $settings->gongOriginalFilename, (string) $settings->gongMimeType);
    }

    public function storePlayer(Request $request): void
    {
        try {
            $result = $this->commands->dispatch(new SaveAudioPlayer(null, $request->input('name', '') ?? '', true));
            $this->rememberToken($result['id'], (string) $result['token']);
            Flash::success('Das Abspielgerät wurde angelegt. Trage den Token jetzt auf dem Raspberry Pi ein – er wird nur einmal angezeigt.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }
        Response::redirect(self::URL . '#geraete');
    }

    public function updatePlayer(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new SaveAudioPlayer($request->param('id'), $request->input('name', '') ?? '', $request->input('enabled') === '1')), 'Das Abspielgerät wurde gespeichert.', '#geraete');
    }

    public function regenerateToken(Request $request): void
    {
        try {
            $token = $this->commands->dispatch(new RegenerateAudioPlayerToken($request->param('id')));
            $this->rememberToken($request->param('id'), (string) $token);
            Flash::success('Neuer Token erzeugt. Der alte Token funktioniert ab sofort nicht mehr.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect(self::URL . '#geraete');
    }

    public function deletePlayer(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new DeleteAudioPlayer($request->param('id'))), 'Das Abspielgerät wurde gelöscht.', '#geraete');
    }

    private function rememberToken(string $playerId, string $token): void
    {
        Session::ensureStarted();
        $_SESSION[self::SESSION_TOKEN] = ['player' => $playerId, 'token' => $token];
    }

    private function run(callable $action, string $success, string $anchor = ''): void
    {
        try {
            $action();
            Flash::success($success);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect(self::URL . $anchor);
    }
}
