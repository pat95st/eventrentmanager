<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\ReceiptInbox\Command\AddAllowedSender;
use App\Application\ReceiptInbox\Command\DeleteAllowedSender;
use App\Application\ReceiptInbox\Query\ListAllowedSenders;
use App\Application\Settings\Command\TestMailboxConnection;
use App\Application\Settings\Command\UpdateMailboxSettings;
use App\Application\Settings\Query\GetMailboxSettings;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use App\Shared\ProjectPaths;
use function App\Infrastructure\Http\t;

final class MailboxSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render([]);
    }

    public function update(Request $request): void
    {
        $command = new UpdateMailboxSettings(
            protocol: $request->input('protocol', '') ?? '',
            host: $request->input('host', '') ?? '',
            port: $request->input('port', '') ?? '',
            encryption: $request->input('encryption', '') ?? '',
            username: $request->input('username'),
            password: $request->input('password'),
            imapFolder: $request->input('imap_folder', '') ?? '',
            enabled: $request->input('enabled') !== null,
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.mailbox.flash.saved'));
        } catch (ValidationException $e) {
            $this->render($e->errors());

            return;
        }

        Response::redirect('/einstellungen/mailserver');
    }

    public function sendTest(Request $request): void
    {
        try {
            $this->commands->dispatch(new TestMailboxConnection());
            Flash::success(t('settings.mailbox.test.flash.success'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }

        Response::redirect('/einstellungen/mailserver');
    }

    public function storeAllowedSender(Request $request): void
    {
        try {
            $this->commands->dispatch(new AddAllowedSender(
                email: $request->input('email', '') ?? '',
                note: $request->input('note', '') ?? '',
            ));
            Flash::success(t('settings.mailbox.allowed_senders.flash.added'));
        } catch (ValidationException $e) {
            $this->render([], $e->errors());

            return;
        }

        Response::redirect('/einstellungen/mailserver');
    }

    public function destroyAllowedSender(Request $request): void
    {
        $id = $request->param('id');

        try {
            $this->commands->dispatch(new DeleteAllowedSender($id));
            Flash::success(t('settings.mailbox.allowed_senders.flash.deleted'));
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }

        Response::redirect('/einstellungen/mailserver');
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $allowedSenderErrors
     */
    private function render(array $errors, array $allowedSenderErrors = []): void
    {
        $settings = $this->queries->ask(new GetMailboxSettings());
        $allowedSenders = $this->queries->ask(new ListAllowedSenders());

        echo View::page('settings/mailbox', [
            'settings' => $settings,
            'errors' => $errors,
            'allowedSenders' => $allowedSenders,
            'allowedSenderErrors' => $allowedSenderErrors,
            'cronScriptPath' => ProjectPaths::importReceiptEmailsScriptPath(),
        ], activeNav: 'settings-mailserver', title: t('settings.mailbox.title'));
    }
}
