<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateAiAdImageSettings;
use App\Application\Settings\Query\GetAiAdImageSettings;
use App\Domain\Settings\AiAdImageSettings;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/** Zugangsdaten für den KI-Werbebildgenerator (siehe App\Domain\Settings\AiAdImageSettings). */
final class AiAdImageSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetAiAdImageSettings());
        assert($settings instanceof AiAdImageSettings);

        $this->render($settings);
    }

    public function update(Request $request): void
    {
        $this->commands->dispatch(new UpdateAiAdImageSettings(
            openaiApiKey: $request->input('openai_api_key'),
        ));

        Flash::success(t('settings.ai_ad_images.flash.saved'));
        Response::redirect('/einstellungen/ki-werbebilder');
    }

    private function render(AiAdImageSettings $settings): void
    {
        echo View::page('settings/ai-ad-images', [
            'settings' => $settings,
        ], activeNav: 'settings-ki-werbebilder', title: t('settings.ai_ad_images.title'));
    }
}
