<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\MinuteProgram\Command\AddMinuteProgramTemplateItem;
use App\Application\MinuteProgram\Command\CreateMinuteProgramTemplate;
use App\Application\MinuteProgram\Command\DeleteMinuteProgramTemplate;
use App\Application\MinuteProgram\Command\DeleteMinuteProgramTemplateItem;
use App\Application\MinuteProgram\Command\MoveMinuteProgramTemplateItem;
use App\Application\MinuteProgram\Command\UpdateMinuteProgramTemplate;
use App\Application\MinuteProgram\Command\UpdateMinuteProgramTemplateItem;
use App\Application\MinuteProgram\Query\GetMinuteProgramTemplateDetail;
use App\Application\MinuteProgram\Query\ListMinuteProgramTemplates;
use App\Application\Query\QueryBus;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/** Wiederverwendbare Minutenprogramm-Vorlagen (Stammdaten, unabhängig von einer konkreten Veranstaltung). */
final class MinuteProgramTemplateController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $templates = $this->queries->ask(new ListMinuteProgramTemplates());

        echo View::page('minute-program-templates/index', [
            'templates' => $templates,
        ], activeNav: 'minute_program_templates', title: t('minute_program_templates.title'));
    }

    public function create(Request $request): void
    {
        echo View::page('minute-program-templates/form', [
            'template' => null,
            'errors' => [],
        ], activeNav: 'minute_program_templates', title: t('minute_program_templates.new'));
    }

    public function store(Request $request): void
    {
        try {
            $templateId = $this->commands->dispatch(new CreateMinuteProgramTemplate(
                name: $request->input('name', '') ?? '',
                jingleBefore: $request->input('jingle_before', '0') === '1',
                handheldMicCount: (int) ($request->input('handheld_mic_count', '0') ?? '0'),
                headsetMicCount: (int) ($request->input('headset_mic_count', '0') ?? '0'),
            ));
            Flash::success(t('minute_program_templates.flash.created'));
            Response::redirect('/minutenprogramm-vorlagen/' . $templateId);
        } catch (ValidationException $e) {
            echo View::page('minute-program-templates/form', [
                'template' => null,
                'errors' => $e->errors(),
            ], activeNav: 'minute_program_templates', title: t('minute_program_templates.new'));
        }
    }

    public function show(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetMinuteProgramTemplateDetail($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::page('minute-program-templates/show', [
            'detail' => $detail,
        ], activeNav: 'minute_program_templates', title: $detail->template->name);
    }

    public function edit(Request $request): void
    {
        $id = $request->param('id');

        try {
            $detail = $this->queries->ask(new GetMinuteProgramTemplateDetail($id));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        echo View::page('minute-program-templates/form', [
            'template' => $detail->template,
            'errors' => [],
        ], activeNav: 'minute_program_templates', title: t('minute_program_templates.edit'));
    }

    public function update(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new UpdateMinuteProgramTemplate(
                templateId: $id,
                name: $request->input('name', '') ?? '',
                jingleBefore: $request->input('jingle_before', '0') === '1',
                handheldMicCount: (int) ($request->input('handheld_mic_count', '0') ?? '0'),
                headsetMicCount: (int) ($request->input('headset_mic_count', '0') ?? '0'),
            ));
            Flash::success(t('minute_program_templates.flash.updated'));
            Response::redirect('/minutenprogramm-vorlagen/' . $id);
        } catch (ValidationException $e) {
            echo View::page('minute-program-templates/form', [
                'template' => null,
                'errors' => $e->errors(),
            ], activeNav: 'minute_program_templates', title: t('minute_program_templates.edit'));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function destroy(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteMinuteProgramTemplate($id));
            Flash::success(t('minute_program_templates.flash.deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/minutenprogramm-vorlagen');
    }

    public function addItem(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new AddMinuteProgramTemplateItem(
                templateId: $id,
                name: $request->input('name', '') ?? '',
                description: $request->input('description'),
                noteGeneral: $request->input('note_general'),
                noteSound: $request->input('note_sound'),
                noteLight: $request->input('note_light'),
                noteVideo: $request->input('note_video'),
                noteStage: $request->input('note_stage'),
                durationMinutes: self::nullableInt($request->input('duration_minutes')),
            ));
            Flash::success(t('minute_program_templates.flash.item_added'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/minutenprogramm-vorlagen/' . $id);
    }

    public function updateItem(Request $request): void
    {
        $itemId = $request->param('id');
        $templateId = $request->input('template_id', '') ?? '';

        try {
            $this->commands->dispatch(new UpdateMinuteProgramTemplateItem(
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
            Flash::success(t('minute_program_templates.flash.item_updated'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (NotFoundException $e) {
            Response::notFound($e->getMessage());

            return;
        }

        Response::redirect('/minutenprogramm-vorlagen/' . $templateId);
    }

    public function deleteItem(Request $request): void
    {
        $itemId = $request->param('id');
        $templateId = $request->input('template_id', '') ?? '';

        try {
            $this->commands->dispatch(new DeleteMinuteProgramTemplateItem($itemId));
            Flash::success(t('minute_program_templates.flash.item_deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/minutenprogramm-vorlagen/' . $templateId);
    }

    public function moveItem(Request $request): void
    {
        $itemId = $request->param('id');
        $templateId = $request->input('template_id', '') ?? '';
        $direction = $request->input('direction', 'down') ?? 'down';

        try {
            $this->commands->dispatch(new MoveMinuteProgramTemplateItem($itemId, $direction));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/minutenprogramm-vorlagen/' . $templateId);
    }

    private static function nullableInt(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return (int) $value;
    }
}
