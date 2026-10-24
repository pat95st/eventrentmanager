<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\SendTestMail;
use App\Application\Settings\Command\UpdateSmtpSettings;
use App\Application\Settings\Query\GetSmtpSettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class SmtpSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetSmtpSettings());

        echo View::page('settings/smtp', [
            'settings' => $settings,
            'errors' => [],
            'testAddress' => CurrentUser::get()?->email ?? '',
        ], activeNav: 'settings-smtp', title: t('settings.smtp.title'));
    }

    public function update(Request $request): void
    {
        $command = new UpdateSmtpSettings(
            host: $request->input('host', '') ?? '',
            port: $request->input('port', '') ?? '',
            encryption: $request->input('encryption', '') ?? '',
            username: $request->input('username'),
            password: $request->input('password'),
            fromAddress: $request->input('from_address', '') ?? '',
            fromName: $request->input('from_name', '') ?? '',
            replyToAddress: $request->input('reply_to_address'),
            archiveBccAddress: $request->input('archive_bcc_address'),
            archiveCategories: $request->inputArray('archive_categories'),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.smtp.flash.saved'));
            Response::redirect('/einstellungen/smtp');
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetSmtpSettings());
            echo View::page('settings/smtp', [
                'settings' => $settings,
                'errors' => $e->errors(),
                'testAddress' => CurrentUser::get()?->email ?? '',
            ], activeNav: 'settings-smtp', title: t('settings.smtp.title'));
        }
    }

    public function sendTest(Request $request): void
    {
        try {
            $this->commands->dispatch(new SendTestMail($request->input('test_address', '') ?? ''));
            Flash::success(t('settings.smtp.flash.test_sent'));
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }

        Response::redirect('/einstellungen/smtp');
    }
}
