<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Messaging\Command\TestMessagingConnection;
use App\Application\Messaging\Command\UpdateMessagingSettings;
use App\Application\Messaging\Query\GetMessagingSettings;
use App\Application\Query\QueryBus;
use App\Domain\Exception\ValidationException;
use App\Domain\Messaging\MessagingChannel;
use App\Domain\Messaging\MessagingSettings;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/** Zugangsdaten + Aktivierungsschalter für WhatsApp Business/Telegram/Signal (siehe App\Domain\Messaging\MessagingSettings). */
final class MessagingSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetMessagingSettings());
        assert($settings instanceof MessagingSettings);

        $this->render($settings, []);
    }

    public function update(Request $request): void
    {
        $command = new UpdateMessagingSettings(
            whatsappEnabled: $request->input('whatsapp_enabled') !== null,
            whatsappPhoneNumberId: $request->input('whatsapp_phone_number_id'),
            whatsappAccessToken: $request->input('whatsapp_access_token'),
            whatsappBusinessAccountId: $request->input('whatsapp_business_account_id'),
            telegramEnabled: $request->input('telegram_enabled') !== null,
            telegramBotToken: $request->input('telegram_bot_token'),
            signalEnabled: $request->input('signal_enabled') !== null,
            signalApiBaseUrl: $request->input('signal_api_base_url'),
            signalSenderNumber: $request->input('signal_sender_number'),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.messaging.flash.saved'));
            Response::redirect('/einstellungen/messenger');
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetMessagingSettings());
            assert($settings instanceof MessagingSettings);
            $this->render($settings, $e->errors());
        }
    }

    public function testConnection(Request $request): void
    {
        $channel = MessagingChannel::tryFrom($request->param('channel'));
        if ($channel === null) {
            Response::notFound(t('settings.messaging.error.unknown_channel'));

            return;
        }

        try {
            /** @var string $resultMessage */
            $resultMessage = $this->commands->dispatch(new TestMessagingConnection($channel));
            Flash::success($resultMessage);
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }

        Response::redirect('/einstellungen/messenger');
    }

    /** @param array<string, string> $errors */
    private function render(MessagingSettings $settings, array $errors): void
    {
        echo View::page('settings/messaging/edit', [
            'settings' => $settings,
            'errors' => $errors,
        ], activeNav: 'settings-messenger', title: t('settings.messaging.title'));
    }
}
