<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\MinuteProgram\Query\ResolvedMinuteProgramShareLink;
use App\Application\MinuteProgram\Query\ResolveMinuteProgramShareLink;
use App\Application\Query\QueryBus;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

/**
 * Run sheet API for stage displays and other external clients
 * (migration 158), see docs/stagedisplay-api.md.
 *
 * Authentication: the token of a minute program share link, in the path
 * (/api/runsheet/v1/{token}) – "read" links may only read, "read/write"
 * links may also control the live state. Revoking the link revokes access.
 * No session/cookies are involved, hence CSRF-exempt (Router) and CORS is
 * open so a browser-based stage display on another origin can poll it.
 */
final class RunSheetApiController
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly RunSheetActions $actions,
    ) {
    }

    public function state(Request $request): void
    {
        $resolved = $this->resolve($request);
        if ($resolved !== null) {
            self::json($this->actions->payload($resolved->detail, $resolved->link->mode->canEdit(), RunSheetActions::AUDIENCE_STAGE));
        }
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
        $resolved = $this->resolve($request);
        if ($resolved === null) {
            return;
        }
        if (!$resolved->link->mode->canEdit()) {
            self::json(['ok' => false, 'error' => 'Dieser Freigabe-Link erlaubt nur das Lesen.'], 403);

            return;
        }
        $error = $this->actions->perform($action, $resolved->detail, RunSheetActions::input($request), $itemId);
        if ($error !== null) {
            self::json(['ok' => false, 'error' => $error[0]], $error[1]);

            return;
        }
        $fresh = $this->queries->ask(new ResolveMinuteProgramShareLink($request->param('token')));
        self::json(['ok' => true] + $this->actions->payload($fresh->detail, true, RunSheetActions::AUDIENCE_STAGE));
    }

    private function resolve(Request $request): ?ResolvedMinuteProgramShareLink
    {
        try {
            return $this->queries->ask(new ResolveMinuteProgramShareLink($request->param('token')));
        } catch (NotFoundException) {
            self::json(['ok' => false, 'error' => 'Unbekannter oder widerrufener Token.'], 404);

            return null;
        }
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
        header('Access-Control-Allow-Headers: Content-Type');
    }
}
