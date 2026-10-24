<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\MinuteProgram\Command\SetMinuteProgramDelay;
use App\Application\MinuteProgram\Query\CountdownPayload;
use App\Application\MinuteProgram\Query\ResolveMinuteProgramShareLink;
use App\Application\Query\QueryBus;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Öffentlich erreichbare (nicht angemeldete!) Ansichten eines Minutenprogramms
 * über einen Freigabe-Link-Token — z. B. für Technik-Dienstleister oder
 * Veranstalter ohne eigenes Benutzerkonto. Analog CustomerChecklistController:
 * eigene, schlanke Seiten ohne die normale Navigation. Bei ShareLinkMode::
 * ReadWrite darf zusätzlich die Live-Verzögerung gesetzt werden.
 */
final class MinuteProgramShareController
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly CommandBus $commands,
    ) {
    }

    public function show(Request $request): void
    {
        $token = $request->param('token');

        try {
            $resolved = $this->queries->ask(new ResolveMinuteProgramShareLink($token));
        } catch (NotFoundException) {
            // Bewusst keine Unterscheidung nach außen, ob der Token nie
            // existiert hat oder widerrufen wurde (analog Kundencheckliste).
            http_response_code(404);
            echo View::render('public/minute-program-share-unavailable');

            return;
        }

        echo View::render('public/minute-program-share', [
            'link' => $resolved->link,
            'detail' => $resolved->detail,
            'token' => $token,
        ]);
    }

    /** Vollbild-Countdown-Ansicht ohne Steuerungselemente (z. B. als Bühnenmonitor). */
    public function fullscreen(Request $request): void
    {
        $token = $request->param('token');

        try {
            $resolved = $this->queries->ask(new ResolveMinuteProgramShareLink($token));
        } catch (NotFoundException) {
            http_response_code(404);
            echo View::render('public/minute-program-share-unavailable');

            return;
        }

        echo View::render('public/minute-program-fullscreen', [
            'detail' => $resolved->detail,
            'token' => $token,
        ]);
    }

    /** JSON-Polling-Endpunkt für die Live-/Vollbild-Ansicht per Freigabe-Link. */
    public function countdown(Request $request): void
    {
        $token = $request->param('token');

        try {
            $resolved = $this->queries->ask(new ResolveMinuteProgramShareLink($token));
        } catch (NotFoundException $e) {
            Response::json(['error' => $e->getMessage()], 404);

            return;
        }

        Response::json(CountdownPayload::build($resolved->detail));
    }

    /** Live-Verzögerung setzen — nur erlaubt, wenn der Link im Modus ReadWrite ist. */
    public function setDelay(Request $request): void
    {
        $token = $request->param('token');

        try {
            $resolved = $this->queries->ask(new ResolveMinuteProgramShareLink($token));
        } catch (NotFoundException $e) {
            Response::json(['ok' => false, 'error' => $e->getMessage()], 404);

            return;
        }

        if (!$resolved->link->mode->canEdit()) {
            Response::json(['ok' => false, 'error' => 'Dieser Freigabe-Link erlaubt keine Änderungen.'], 403);

            return;
        }

        $delay = (int) ($request->input('current_delay_minutes', '0') ?? '0');
        $this->commands->dispatch(new SetMinuteProgramDelay($resolved->detail->program->id, $delay));

        Response::json(['ok' => true]);
    }
}
