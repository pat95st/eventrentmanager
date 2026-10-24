<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Messaging\Command\ResetMessageTemplate;
use App\Application\Messaging\Command\SaveMessageTemplate;
use App\Application\Messaging\Query\GetMessageTemplate;
use App\Application\Messaging\Query\ListMessageTemplates;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ValidationException;
use App\Domain\Messaging\MessageTemplate;
use App\Domain\Messaging\MessageTemplateKey;
use App\Domain\Messaging\MessagingChannel;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/** Admin-bearbeitbare Vorlagen je Messenger — siehe App\Domain\Messaging\MessageTemplateKey für den Unterschied zwischen WhatsApp (Meta-Vorlagenname) und Telegram/Signal (Klartext). */
final class MessageTemplateSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function index(Request $request): void
    {
        $channel = MessagingChannel::tryFrom($request->query('kanal') ?? '') ?? MessagingChannel::WhatsApp;

        echo View::page('settings/messaging-templates/index', [
            'channel' => $channel,
            'templates' => $this->queries->ask(new ListMessageTemplates($channel)),
        ], activeNav: 'settings-messenger-vorlagen', title: t('settings.messaging_templates.title'));
    }

    public function edit(Request $request): void
    {
        [$channel, $key] = $this->resolveChannelAndKey($request);
        if ($channel === null || $key === null) {
            Response::notFound(t('settings.messaging_templates.error.unknown_key'));

            return;
        }

        $template = $this->queries->ask(new GetMessageTemplate($channel, $key));
        assert($template instanceof MessageTemplate);

        $this->renderTemplate($channel, $key, $template, []);
    }

    public function update(Request $request): void
    {
        [$channel, $key] = $this->resolveChannelAndKey($request);
        if ($channel === null || $key === null) {
            Response::notFound(t('settings.messaging_templates.error.unknown_key'));

            return;
        }

        $command = new SaveMessageTemplate(
            channel: $channel,
            key: $key,
            bodyText: $request->input('body_text'),
            metaTemplateName: $request->input('meta_template_name'),
            metaTemplateLanguage: $request->input('meta_template_language'),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.messaging_templates.flash.saved'));
            Response::redirect('/einstellungen/messenger-vorlagen/' . $channel->value . '/' . $key->value);
        } catch (ValidationException $e) {
            // Zeigt die soeben abgesendeten (fehlerhaften) Werte erneut an,
            // statt der zuletzt gespeicherten Vorlage.
            $template = new MessageTemplate(
                $channel,
                $key,
                bodyText: $command->bodyText,
                metaTemplateName: $command->metaTemplateName,
                metaTemplateLanguage: $command->metaTemplateLanguage !== null && trim($command->metaTemplateLanguage) !== '' ? $command->metaTemplateLanguage : 'de',
            );
            $this->renderTemplate($channel, $key, $template, $e->errors());
        }
    }

    public function reset(Request $request): void
    {
        [$channel, $key] = $this->resolveChannelAndKey($request);
        if ($channel === null || $key === null) {
            Response::notFound(t('settings.messaging_templates.error.unknown_key'));

            return;
        }

        $this->commands->dispatch(new ResetMessageTemplate($channel, $key));
        Flash::success(t('settings.messaging_templates.flash.reset'));
        Response::redirect('/einstellungen/messenger-vorlagen/' . $channel->value . '/' . $key->value);
    }

    /** @return array{0: MessagingChannel|null, 1: MessageTemplateKey|null} */
    private function resolveChannelAndKey(Request $request): array
    {
        $channel = MessagingChannel::tryFrom($request->param('channel'));
        $key = MessageTemplateKey::tryFrom($request->param('key'));

        if ($channel === null || $key === null || !$key->appliesToChannel($channel)) {
            return [null, null];
        }

        return [$channel, $key];
    }

    /** @param array<string, string> $errors */
    private function renderTemplate(MessagingChannel $channel, MessageTemplateKey $key, MessageTemplate $template, array $errors): void
    {
        echo View::page('settings/messaging-templates/edit', [
            'channel' => $channel,
            'key' => $key,
            'template' => $template,
            'errors' => $errors,
        ], activeNav: 'settings-messenger-vorlagen', title: t('settings.messaging_templates.title'));
    }
}
