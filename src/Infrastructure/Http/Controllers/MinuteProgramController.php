<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\MinuteProgram\Command\AddMinuteProgramItem;
use App\Application\MinuteProgram\Command\CreateMinuteProgram;
use App\Application\MinuteProgram\Command\CreateMinuteProgramFromTemplate;
use App\Application\MinuteProgram\Command\CreateMinuteProgramShareLink;
use App\Application\MinuteProgram\Command\CreateMinuteProgramTemplateFromProgram;
use App\Application\MinuteProgram\Command\DeleteMinuteProgram;
use App\Application\MinuteProgram\Command\DeleteMinuteProgramItem;
use App\Application\MinuteProgram\Command\MoveMinuteProgramItem;
use App\Application\MinuteProgram\Command\RevokeMinuteProgramShareLink;
use App\Application\MinuteProgram\Command\SetMinuteProgramDelay;
use App\Application\MinuteProgram\Command\UpdateMinuteProgram;
use App\Application\MinuteProgram\Command\UpdateMinuteProgramItem;
use App\Application\MinuteProgram\Query\CountdownPayload;
use App\Application\MinuteProgram\Query\GetMinuteProgramDetail;
use App\Application\MinuteProgram\Query\ListMinuteProgramsForRental;
use App\Application\MinuteProgram\Query\ListMinuteProgramTemplates;
use App\Application\Query\QueryBus;
use App\Application\Rental\Query\GetRental;
use App\Domain\MinuteProgram\MinuteProgramShareLinkRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/**
 * Minutenprogramme (Ablaufpläne) einer Veranstaltung: Kopfdaten, Programmpunkte,
 * Live-Verzögerung und Freigabe-Links. Die Vollbild-Countdown-Ansicht und die
 * öffentliche Freigabe-Ansicht liegen in eigenen, schlanken Controllern
 * (siehe MinuteProgramShareController), da sie ohne normale Navigation
 * auskommen bzw. unangemeldet erreichbar sind.
 */
final class MinuteProgramController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly MinuteProgramShareLinkRepositoryInterface $shareLinks,
    ) {
    }

    public function create(Request $request): void
    {
        $rentalId = $request->param('rentalId');

        try {
            $detail = $this->queries->ask(new GetRental($rentalId));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $templates = $this->queries->ask(new ListMinuteProgramTemplates());

        echo View::page('minute-programs/form', [
            'rental' => $detail->rental,
            'program' => null,
            'templates' => $templates,
            'errors' => [],
            'old' => [],
        ], activeNav: 'rentals', title: t('minute_programs.new'));
    }

    public function store(Request $request): void
    {
        $rentalId = $request->param('rentalId');
        $templateId = trim($request->input('template_id', '') ?? '');

        try {
            if ($templateId !== '') {
                $programId = $this->commands->dispatch(new CreateMinuteProgramFromTemplate(
                    templateId: $templateId,
                    rentalId: $rentalId,
                    name: $request->input('name', '') ?? '',
                    room: $request->input('room'),
                    startAt: $request->input('start_at', '') ?? '',
                ));
            } else {
                $programId = $this->commands->dispatch(new CreateMinuteProgram(
                    rentalId: $rentalId,
                    name: $request->input('name', '') ?? '',
                    room: $request->input('room'),
                    startAt: $request->input('start_at', '') ?? '',
                    jingleBefore: $request->input('jingle_before', '0') === '1',
                    handheldMicCount: (int) ($request->input('handheld_mic_count', '0') ?? '0'),
                    headsetMicCount: (int) ($request->input('headset_mic_count', '0') ?? '0'),
                ));
            }

            Flash::success(t('minute_programs.flash.created'));
            Response::redirect('/minutenprogramme/' . $programId);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
            Response::redirect('/veranstaltungen/' . $rentalId . '/minutenprogramme/neu');
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function show(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetMinuteProgramDetail($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        $rental = $this->queries->ask(new GetRental($detail->program->rentalId))->rental;
        $links = $this->shareLinks->findByMinuteProgram($detail->program->id);

        echo View::page('minute-programs/show', [
            'detail' => $detail,
            'rental' => $rental,
            'shareLinks' => $links,
        ], activeNav: 'rentals', title: $detail->program->name);
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetMinuteProgramDetail($id));
            $rental = $this->queries->ask(new GetRental($detail->program->rentalId))->rental;
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::page('minute-programs/form', [
            'rental' => $rental,
            'program' => $detail->program,
            'templates' => [],
            'errors' => [],
            'old' => [],
        ], activeNav: 'rentals', title: t('minute_programs.edit'));
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new UpdateMinuteProgram(
                minuteProgramId: $id,
                name: $request->input('name', '') ?? '',
                room: $request->input('room'),
                startAt: $request->input('start_at', '') ?? '',
                jingleBefore: $request->input('jingle_before', '0') === '1',
                handheldMicCount: (int) ($request->input('handheld_mic_count', '0') ?? '0'),
                headsetMicCount: (int) ($request->input('headset_mic_count', '0') ?? '0'),
            ));
            Flash::success(t('minute_programs.flash.updated'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/minutenprogramme/' . $id);
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetMinuteProgramDetail($id));
            $rentalId = $detail->program->rentalId;
            $this->commands->dispatch(new DeleteMinuteProgram($id));
            Flash::success(t('minute_programs.flash.deleted'));
            Response::redirect('/veranstaltungen/' . $rentalId);
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    /** Live-Verzögerung (auch negativ) setzen — verschiebt nur die Anzeige, nicht den ursprünglichen Plan. */
    public function setDelay(Request $request): void
    {
        $id = $request->param('id');
        $delay = (int) ($request->input('current_delay_minutes', '0') ?? '0');

        try {
            $this->commands->dispatch(new SetMinuteProgramDelay($id, $delay));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        if ($request->input('ajax', '0') === '1') {
            Response::json(['ok' => true]);

            return;
        }

        Response::redirect('/minutenprogramme/' . $id);
    }

    /** Vollbild-Countdown-Ansicht für angemeldete Benutzer (z. B. als Bühnenmonitor), analog MinuteProgramShareController::fullscreen(). */
    public function fullscreen(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetMinuteProgramDetail($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::render('minute-programs/fullscreen', [
            'detail' => $detail,
        ]);
    }

    /** JSON-Polling-Endpunkt für die Vollbild-Countdown-Ansicht. */
    public function countdown(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetMinuteProgramDetail($id));
        } catch (NotFoundException $e) {
            Response::json(['error' => $e->getMessage()], 404);

            return;
        }

        Response::json(CountdownPayload::build($detail));
    }

    public function addItem(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new AddMinuteProgramItem(
                minuteProgramId: $id,
                name: $request->input('name', '') ?? '',
                description: $request->input('description'),
                noteGeneral: $request->input('note_general'),
                noteSound: $request->input('note_sound'),
                noteLight: $request->input('note_light'),
                noteVideo: $request->input('note_video'),
                noteStage: $request->input('note_stage'),
                durationMinutes: self::nullableInt($request->input('duration_minutes')),
            ));
            Flash::success(t('minute_programs.flash.item_added'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/minutenprogramme/' . $id);
    }

    public function updateItem(Request $request): void
    {
        $itemId = $request->param('id');
        $programId = $request->input('minute_program_id', '') ?? '';

        try {
            $this->commands->dispatch(new UpdateMinuteProgramItem(
                itemId: $itemId,
                name: $request->input('name', '') ?? '',
                description: $request->input('description'),
                noteGeneral: $request->input('note_general'),
                noteSound: $request->input('note_sound'),
                noteLight: $request->input('note_light'),
                noteVideo: $request->input('note_video'),
                noteStage: $request->input('note_stage'),
                durationMinutes: self::nullableInt($request->input('duration_minutes')),
            ));
            Flash::success(t('minute_programs.flash.item_updated'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/minutenprogramme/' . $programId);
    }

    public function deleteItem(Request $request): void
    {
        $itemId = $request->param('id');
        $programId = $request->input('minute_program_id', '') ?? '';

        try {
            $this->commands->dispatch(new DeleteMinuteProgramItem($itemId));
            Flash::success(t('minute_programs.flash.item_deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/minutenprogramme/' . $programId);
    }

    public function moveItem(Request $request): void
    {
        $itemId = $request->param('id');
        $programId = $request->input('minute_program_id', '') ?? '';
        $direction = $request->input('direction', 'down') ?? 'down';

        try {
            $this->commands->dispatch(new MoveMinuteProgramItem($itemId, $direction));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/minutenprogramme/' . $programId);
    }

    /** Speichert dieses konkrete Minutenprogramm als wiederverwendbare Vorlage (siehe CreateMinuteProgramTemplateFromProgramHandler). */
    public function saveAsTemplate(Request $request): void
    {
        $id = $request->param('id');
        $name = $request->input('template_name', '') ?? '';

        try {
            $this->commands->dispatch(new CreateMinuteProgramTemplateFromProgram($id, $name));
            Flash::success(t('minute_programs.flash.template_saved'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/minutenprogramme/' . $id);
    }

    public function createShareLink(Request $request): void
    {
        $id = $request->param('id');
        $mode = $request->input('mode', 'read') ?? 'read';

        try {
            $this->commands->dispatch(new CreateMinuteProgramShareLink($id, $mode));
            Flash::success(t('minute_programs.flash.share_link_created'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/minutenprogramme/' . $id);
    }

    public function revokeShareLink(Request $request): void
    {
        $linkId = $request->param('id');
        $programId = $request->input('minute_program_id', '') ?? '';

        try {
            $this->commands->dispatch(new RevokeMinuteProgramShareLink($linkId));
            Flash::success(t('minute_programs.flash.share_link_revoked'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/minutenprogramme/' . $programId);
    }

    private static function nullableInt(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (int) $value;
    }
}
