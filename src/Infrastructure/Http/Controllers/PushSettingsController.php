<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\EnsureVapidKeysGenerated;
use App\Application\Settings\Command\UpdatePushSettings;
use App\Application\Settings\Query\GetPushSettings;
use App\Domain\Exception\ValidationException;
use App\Domain\Push\PushSettings;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/**
 * Admin-Einstellungsseite für den Web-Push-Versand (siehe
 * App\Infrastructure\Push\WebPushSender): erzeugt bei Bedarf einmalig das
 * anwendungsweite VAPID-Schlüsselpaar (siehe EnsureVapidKeysGeneratedHandler)
 * und verwaltet die Absender-E-Mail-Adresse (JWT-"sub"-Claim, RFC 8292).
 */
final class PushSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->commands->dispatch(new EnsureVapidKeysGenerated());
        assert($settings instanceof PushSettings);

        $this->render($settings, []);
    }

    public function update(Request $request): void
    {
        try {
            $this->commands->dispatch(new UpdatePushSettings(
                subjectEmail: $request->input('subject_email', '') ?? '',
            ));
            Flash::success(t('settings.push.flash.saved'));
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetPushSettings());
            assert($settings instanceof PushSettings);
            $this->render($settings, $e->errors());

            return;
        }

        Response::redirect('/einstellungen/push');
    }

    /** @param array<string, string> $errors */
    private function render(PushSettings $settings, array $errors): void
    {
        // Der Formular-Eingabewert enthält nur die reine Adresse, das
        // "mailto:"-Präfix wird beim Speichern serverseitig ergänzt (siehe
        // UpdatePushSettingsHandler).
        $subjectEmailInput = $settings->subjectEmail !== null
            ? preg_replace('/^mailto:/i', '', $settings->subjectEmail)
            : '';

        echo View::page('settings/push', [
            'settings' => $settings,
            'subjectEmailInput' => $subjectEmailInput,
            'errors' => $errors,
        ], activeNav: 'settings-push', title: t('settings.push.title'));
    }
}
