<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdatePricingSettings;
use App\Application\Settings\Query\GetPricingSettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class PricingSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetPricingSettings());

        echo View::page('settings/pricing', [
            'settings' => $settings,
            'errors' => [],
            'old' => [],
        ], activeNav: 'settings-preise', title: t('settings.pricing.title'));
    }

    public function update(Request $request): void
    {
        $command = new UpdatePricingSettings(
            accessoryFlatRatePercent: $request->input('accessory_flat_rate_percent', '') ?? '',
            priceRoundingStep: $request->input('price_rounding_step', '') ?? '',
            priceRoundingDirection: $request->input('price_rounding_direction', '') ?? '',
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.pricing.flash.saved'));
            Response::redirect('/einstellungen/preise');
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetPricingSettings());
            echo View::page('settings/pricing', [
                'settings' => $settings,
                'errors' => $e->errors(),
                'old' => $request->post,
            ], activeNav: 'settings-preise', title: t('settings.pricing.title'));
        }
    }
}
