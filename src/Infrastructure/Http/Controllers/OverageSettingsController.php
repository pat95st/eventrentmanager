<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateOverageSettings;
use App\Application\Settings\Query\GetOverageSettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/**
 * Einstellungsseite für die Regelungen beim Überziehen der Mietzeit (siehe
 * App\Domain\Settings\OverageSettings).
 */
final class OverageSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetOverageSettings());

        echo View::page('settings/overage', [
            'settings' => $settings,
            'errors' => [],
            'old' => [],
        ], activeNav: 'settings-ueberziehung', title: t('settings.overage.title'));
    }

    public function update(Request $request): void
    {
        $command = new UpdateOverageSettings(
            flatFeeEnabled: $request->input('flat_fee_enabled') === '1',
            flatFeeAmount: $request->input('flat_fee_amount', '') ?? '',
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.overage.flash.saved'));
            Response::redirect('/einstellungen/ueberziehung');
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetOverageSettings());
            echo View::page('settings/overage', [
                'settings' => $settings,
                'errors' => $e->errors(),
                'old' => $request->post,
            ], activeNav: 'settings-ueberziehung', title: t('settings.overage.title'));
        }
    }
}
