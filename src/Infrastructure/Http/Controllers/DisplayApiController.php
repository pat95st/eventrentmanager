<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Display\DisplayProgramResolver;
use App\Application\MinuteProgram\Query\MinuteProgramDetail;
use App\Domain\Display\DisplayClient;
use App\Domain\Display\DisplayClientRepositoryInterface;
use App\Domain\Display\DisplayRole;
use App\Domain\Display\DisplaySettings;
use App\Domain\Display\DisplaySettingsRepositoryInterface;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use DateTimeImmutable;

/**
 * Display API for stage and FOH displays (migration 168), see
 * docs/display-api.md.
 *
 * Authentication: "Authorization: Bearer <device token>" (Settings › Stage &
 * FOH displays). One API for both roles: the response carries the role and
 * layout options of the device plus the run sheet (same format as
 * /api/runsheet/v1) of the minute program the device currently shows, so
 * the same client software works on stage and at FOH. Control actions are
 * only allowed for FOH displays with "allow control" enabled.
 *
 * No session/cookies are involved, hence CSRF-exempt (Router) and CORS is
 * open so a browser-based display on another origin can poll it.
 */
final class DisplayApiController
{
    public const API_VERSION = 1;

    public function __construct(
        private readonly DisplayClientRepositoryInterface $clients,
        private readonly DisplaySettingsRepositoryInterface $settings,
        private readonly DisplayProgramResolver $resolver,
        private readonly RunSheetActions $actions,
    ) {
    }

    public function state(Request $request): void
    {
        $client = $this->authenticate();
        if ($client === null) {
            return;
        }
        $now = new DateTimeImmutable();
        $this->clients->touch($client->id, $now, self::clientIp(), self::limit($request->query('version'), 60));
        self::json($this->payload($client, $this->resolver->resolve($client, $now), $now));
    }

    public function delay(Request $request): void
    {
        $this->act($request, 'delay');
    }

    public function start(Request $request): void
    {
        $this->act($request, 'start', $request->param('itemId'));
    }

    public function finish(Request $request): void
    {
        $this->act($request, 'finish');
    }

    public function reset(Request $request): void
    {
        $this->act($request, 'reset');
    }

    /** Marks an organizer message as seen (migration 167 messages). */
    public function acknowledgeMessage(Request $request): void
    {
        $this->act($request, 'nachricht-gesehen', $request->param('messageId'));
    }

    /** Hides an organizer message (also removes it from stage displays). */
    public function closeMessage(Request $request): void
    {
        $this->act($request, 'nachricht-ausblenden', $request->param('messageId'));
    }

    /** Built-in browser client (public page, the token is entered on the device). */
    public function clientPage(Request $request): void
    {
        header('X-Robots-Tag: noindex');
        header('Referrer-Policy: no-referrer');
        echo View::render('display/client');
    }

    /** CORS preflight. */
    public function preflight(Request $request): void
    {
        self::corsHeaders();
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);
        exit;
    }

    private function act(Request $request, string $action, ?string $itemId = null): void
    {
        $client = $this->authenticate();
        if ($client === null) {
            return;
        }
        if (!$client->canControl()) {
            self::json(['ok' => false, 'error' => 'Dieses Display darf den Ablauf nicht steuern.'], 403);

            return;
        }
        $detail = $this->resolver->resolve($client);
        if ($detail === null) {
            self::json(['ok' => false, 'error' => 'Dem Display ist gerade kein Minutenprogramm zugeordnet.'], 409);

            return;
        }
        $error = $this->actions->perform($action, $detail, RunSheetActions::input($request), $itemId, $client->name . ' (FOH-Display)');
        if ($error !== null) {
            self::json(['ok' => false, 'error' => $error[0]], $error[1]);

            return;
        }
        $now = new DateTimeImmutable();
        self::json(['ok' => true] + $this->payload($client, $this->resolver->resolve($client, $now), $now));
    }

    /** @return array<string, mixed> */
    private function payload(DisplayClient $client, ?MinuteProgramDetail $detail, DateTimeImmutable $now): array
    {
        $settings = $this->settings->get();

        return [
            'apiVersion' => self::API_VERSION,
            'serverTime' => $now->format(DATE_ATOM),
            'pollIntervalSeconds' => $settings->pollIntervalSeconds,
            'display' => self::display($client, $settings),
            // Messages: FOH displays see all active ones, stage displays only those sent to the stage.
            'runSheet' => $detail !== null ? $this->actions->payload($detail, $client->canControl(), $client->role === DisplayRole::Foh ? RunSheetActions::AUDIENCE_FOH : RunSheetActions::AUDIENCE_STAGE) : null,
        ];
    }

    /** @return array<string, mixed> */
    public static function display(DisplayClient $client, DisplaySettings $settings): array
    {
        $display = [
            'id' => $client->id,
            'name' => $client->name,
            'role' => $client->role->value,
            'roleLabel' => $client->role->label(),
            'canControl' => $client->canControl(),
            'assignment' => [
                'mode' => $client->minuteProgramId !== null ? 'program' : ($client->rentalId !== null ? 'rental' : 'auto'),
                'rentalId' => $client->rentalId,
                'minuteProgramId' => $client->minuteProgramId,
                'room' => $client->room,
            ],
            'options' => $client->options->toArray(),
            'thresholds' => [
                'warnSeconds' => $settings->warnSeconds,
                'criticalSeconds' => $settings->criticalSeconds,
            ],
        ];
        // Changes whenever the display configuration changes.
        $display['configRevision'] = substr(sha1(json_encode($display, JSON_THROW_ON_ERROR)), 0, 16);

        return $display;
    }

    private function authenticate(): ?DisplayClient
    {
        $token = self::bearerToken();
        $client = $token !== '' ? $this->clients->findByTokenHash(DisplayClient::hashToken($token)) : null;
        if ($client === null || !$client->enabled) {
            header('WWW-Authenticate: Bearer');
            self::json(['ok' => false, 'error' => 'Ungültiger oder deaktivierter Display-Token.'], 401);

            return null;
        }

        return $client;
    }

    private static function bearerToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp((string) $name, 'Authorization') === 0) {
                    $header = (string) $value;
                }
            }
        }

        return preg_match('/^\s*Bearer\s+(\S+)\s*$/i', (string) $header, $m) === 1 ? $m[1] : '';
    }

    private static function clientIp(): ?string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return $ip !== '' ? substr($ip, 0, 45) : null;
    }

    private static function limit(?string $value, int $max): ?string
    {
        $value = $value !== null ? trim($value) : '';

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /** @param array<string, mixed> $data */
    private static function json(array $data, int $status = 200): void
    {
        self::corsHeaders();
        header('Cache-Control: no-store');
        Response::json($data, $status);
    }

    private static function corsHeaders(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
    }
}
