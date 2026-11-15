<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdateAiAdImageSettings;
use App\Application\Settings\Query\GetAiAdImageSettings;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\AiAdImageSettings;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use App\Infrastructure\Integration\OpenAi\OpenAiImageClient;
use RuntimeException;
use function App\Infrastructure\Http\t;

/** Zugangsdaten für den KI-Werbebildgenerator (siehe App\Domain\Settings\AiAdImageSettings). */
final class AiAdImageSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly ?OpenAiImageClient $imageClient = null,
    ) {
    }

    public function edit(Request $request): void
    {
        $this->render($this->currentSettings());
    }

    public function update(Request $request): void
    {
        try {
            $this->commands->dispatch(new UpdateAiAdImageSettings(
                openaiApiKey: $request->input('openai_api_key'),
                imageModel: $request->input('image_model'),
            ));
        } catch (ValidationException $e) {
            $this->render($this->currentSettings(), $e->errors(), (string) $request->input('image_model'));

            return;
        }

        Flash::success(t('settings.ai_ad_images.flash.saved'));
        Response::redirect('/einstellungen/ki-werbebilder');
    }

    private function currentSettings(): AiAdImageSettings
    {
        $settings = $this->queries->ask(new GetAiAdImageSettings());
        assert($settings instanceof AiAdImageSettings);

        return $settings;
    }

    /** @param array<string, string> $errors */
    private function render(AiAdImageSettings $settings, array $errors = [], ?string $submittedModel = null): void
    {
        // Ask OpenAI which image models this key can use; fall back to a
        // static list when no key is set or the request fails.
        $availableModels = [];
        $modelListError = null;
        if ($settings->isConfigured() && $this->imageClient !== null) {
            try {
                $availableModels = $this->imageClient->listImageModels((string) $settings->openaiApiKey);
            } catch (RuntimeException $e) {
                $modelListError = $e->getMessage();
            }
        }
        $modelsFromApi = $availableModels !== [];
        if (!$modelsFromApi) {
            $availableModels = AiAdImageSettings::SUGGESTED_IMAGE_MODELS;
        }

        echo View::page('settings/ai-ad-images', [
            'settings' => $settings,
            'errors' => $errors,
            'imageModelValue' => $submittedModel ?? ($settings->imageModel ?? ''),
            'availableModels' => $availableModels,
            'modelsFromApi' => $modelsFromApi,
            'modelListError' => $modelListError,
        ], activeNav: 'settings-ki-werbebilder', title: t('settings.ai_ad_images.title'));
    }
}
