<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Display\Command\DeleteDisplayClient;
use App\Application\Display\Command\RegenerateDisplayClientToken;
use App\Application\Display\Command\SaveDisplayClient;
use App\Application\Display\Command\SaveDisplaySettings;
use App\Application\Display\DisplayProgramResolver;
use App\Application\Display\DisplayTargets;
use App\Domain\Display\DisplayClient;
use App\Domain\Display\DisplayClientRepositoryInterface;
use App\Domain\Display\DisplayOptions;
use App\Domain\Display\DisplaySettingsRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Infrastructure\Http\Auth\Session;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Settings › Events & planning › Stage & FOH displays (migration 167):
 * display devices with device token, role (stage/FOH), assignment to an
 * event or minute program and layout options – all on one page, modelled
 * after Settings › Event audio. Devices poll /api/display/v1 (see
 * DisplayApiController, docs/display-api.md).
 */
final class DisplaySettingsController
{
    public const URL = '/einstellungen/displays';
    private const SESSION_TOKEN = 'display_client_new_token';

    public function __construct(
        private readonly CommandBus $commands,
        private readonly DisplayClientRepositoryInterface $clients,
        private readonly DisplaySettingsRepositoryInterface $settings,
        private readonly DisplayTargets $targets,
        private readonly ?DisplayProgramResolver $resolver = null,
        private readonly ?RentalRepositoryInterface $rentals = null,
    ) {
    }

    public function index(Request $request): void
    {
        Session::ensureStarted();
        $newToken = $_SESSION[self::SESSION_TOKEN] ?? null;
        unset($_SESSION[self::SESSION_TOKEN]);
        $clients = $this->clients->findAll();
        $assigned = array_values(array_filter(array_map(static fn (DisplayClient $c): ?string => $c->rentalId, $clients)));

        echo View::page('settings/displays', [
            'clients' => $clients,
            'settings' => $this->settings->get(),
            'targets' => $this->targets->list($assigned),
            'showing' => $this->showing($clients),
            'newToken' => is_array($newToken) ? $newToken : null,
            'origin' => self::origin(),
        ], activeNav: 'settings-displays', title: 'Stage- & FOH-Displays');
    }

    /**
     * What each device currently shows (for the overview).
     *
     * @param DisplayClient[] $clients
     *
     * @return array<string, ?array{program: string, room: ?string, event: ?string}>
     */
    private function showing(array $clients): array
    {
        if ($this->resolver === null) {
            return [];
        }
        $now = new \DateTimeImmutable();
        $result = [];
        foreach ($clients as $client) {
            $detail = $client->enabled ? $this->resolver->resolve($client, $now) : null;
            $result[$client->id] = $detail === null ? null : [
                'program' => $detail->program->name,
                'room' => $detail->program->room,
                'event' => $this->rentals?->findById($detail->program->rentalId)?->eventName,
            ];
        }

        return $result;
    }

    public function store(Request $request): void
    {
        try {
            $result = $this->commands->dispatch(new SaveDisplayClient(
                null,
                $request->input('name', '') ?? '',
                $request->input('role', 'stage') ?? 'stage',
                true,
                $request->input('target', 'auto') ?? 'auto',
                $request->input('room'),
            ));
            $this->rememberToken($result['id'], (string) $result['token']);
            Flash::success('Das Display wurde angelegt. Trage den Token jetzt im Display-Client ein – er wird nur einmal angezeigt.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }
        Response::redirect(self::URL . '#geraete');
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');
        $this->run(fn () => $this->commands->dispatch(new SaveDisplayClient(
            $id,
            $request->input('name', '') ?? '',
            $request->input('role', 'stage') ?? 'stage',
            $request->input('enabled') === '1',
            $request->input('target', 'auto') ?? 'auto',
            $request->input('room'),
            self::optionsFrom($request),
        )), 'Das Display wurde gespeichert.', '#display-' . $id);
    }

    public function regenerateToken(Request $request): void
    {
        $id = $request->param('id');
        try {
            $token = $this->commands->dispatch(new RegenerateDisplayClientToken($id));
            $this->rememberToken($id, (string) $token);
            Flash::success('Neuer Token erzeugt. Der alte Token funktioniert ab sofort nicht mehr.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect(self::URL . '#display-' . $id);
    }

    public function delete(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new DeleteDisplayClient($request->param('id'))), 'Das Display wurde gelöscht.', '#geraete');
    }

    public function saveSettings(Request $request): void
    {
        $this->run(fn () => $this->commands->dispatch(new SaveDisplaySettings(
            (int) ($request->input('poll_interval_seconds', '3') ?? 3),
            (int) ($request->input('warn_seconds', '120') ?? 120),
            (int) ($request->input('critical_seconds', '30') ?? 30),
        )), 'Die Einstellungen wurden gespeichert.', '#einstellungen');
    }

    /** @return array<string, mixed> */
    private static function optionsFrom(Request $request): array
    {
        $flag = static fn (string $field): bool => $request->input($field) === '1';

        return [
            'notes' => $request->inputArray('notes'),
            'showClock' => $flag('show_clock'),
            'showDelay' => $flag('show_delay'),
            'showNext' => $flag('show_next'),
            'showItemList' => $flag('show_item_list'),
            'showDescription' => $flag('show_description'),
            'showMics' => $flag('show_mics'),
            'allowControl' => $flag('allow_control'),
            'theme' => $request->input('theme', 'dark') ?? 'dark',
            'fontScale' => (int) ($request->input('font_scale', '100') ?? 100),
        ];
    }

    private function rememberToken(string $clientId, string $token): void
    {
        Session::ensureStarted();
        $_SESSION[self::SESSION_TOKEN] = ['client' => $clientId, 'token' => $token];
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

    public static function origin(): string
    {
        $https = (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
}
