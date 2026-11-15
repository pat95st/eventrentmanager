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

    /**
     * JSON endpoint for the model picker. Querying OpenAI can take a few
     * seconds, so the settings page loads the list asynchronously and shows
     * a spinner meanwhile instead of blocking the page render.
     */
    public function models(Request $request): void
    {
        $settings = $this->currentSettings();
        $availableModels = [];
        $error = null;
        if ($settings->isConfigured() && $this->imageClient !== null) {
            try {
                $availableModels = $this->imageClient->listImageModels((string) $settings->openaiApiKey);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        $fromApi = $availableModels !== [];
        Response::json([
            'models' => $fromApi ? array_values($availableModels) : AiAdImageSettings::SUGGESTED_IMAGE_MODELS,
            'fromApi' => $fromApi,
            'hint' => $fromApi
                ? t('settings.ai_ad_images.field.image_model_from_api', ['models' => implode(', ', $availableModels)])
                : null,
            'error' => $error !== null
                ? t('settings.ai_ad_images.field.image_model_list_failed', ['error' => $error])
                : null,
        ]);
    }

    /** @param array<string, string> $errors */
    private function render(AiAdImageSettings $settings, array $errors = [], ?string $submittedModel = null): void
    {
        // The page renders with the static suggestions; the models available
        // for the configured key are fetched afterwards via models().
        echo View::page('settings/ai-ad-images', [
            'settings' => $settings,
            'errors' => $errors,
            'imageModelValue' => $submittedModel ?? ($settings->imageModel ?? ''),
            'availableModels' => AiAdImageSettings::SUGGESTED_IMAGE_MODELS,
            'loadModelsFromApi' => $settings->isConfigured() && $this->imageClient !== null,
        ], activeNav: 'settings-ki-werbebilder', title: t('settings.ai_ad_images.title'));
    }
}
