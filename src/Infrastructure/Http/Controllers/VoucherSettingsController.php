<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateVoucherSettings;
use App\Application\Settings\Query\GetVoucherSettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

final class VoucherSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetVoucherSettings());

        echo View::page('settings/vouchers', [
            'settings' => $settings,
            'errors' => [],
        ], activeNav: 'settings-gutscheine', title: 'Gutschein-Einstellungen');
    }

    public function update(Request $request): void
    {
        $command = new UpdateVoucherSettings(
            defaultValidityDays: $request->input('default_validity_days', '') ?? '',
        );

        try {
            $this->commands->dispatch($command);
            Flash::success('Gutschein-Einstellungen wurden gespeichert.');
            Response::redirect('/einstellungen/gutscheine');
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetVoucherSettings());
            echo View::page('settings/vouchers', [
                'settings' => $settings,
                'errors' => $e->errors(),
            ], activeNav: 'settings-gutscheine', title: 'Gutschein-Einstellungen');
        }
    }
}
