<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\DeleteCompanyBackgroundImage;
use App\Application\Settings\Command\DeleteCompanyDarkLogo;
use App\Application\Settings\Command\DeleteCompanyLogo;
use App\Application\Settings\Command\UpdateCompanySettings;
use App\Application\Settings\Command\UploadCompanyBackgroundImage;
use App\Application\Settings\Command\UploadCompanyDarkLogo;
use App\Application\Settings\Command\UploadCompanyLogo;
use App\Application\Settings\Query\GetCompanySettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

final class CompanySettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetCompanySettings());

        echo View::page('settings/company', [
            'settings' => $settings,
            'errors' => [],
        ], activeNav: 'settings-firma', title: t('settings.company.title'));
    }

    public function update(Request $request): void
    {
        $command = new UpdateCompanySettings(
            name: $request->input('name'),
            brandName: $request->input('brand_name'),
            street: $request->input('street'),
            postalCode: $request->input('postal_code'),
            city: $request->input('city'),
            country: $request->input('country'),
            logoMaxHeightPx: $request->input('logo_max_height_px'),
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.company.flash.saved'));
            Response::redirect('/einstellungen/firma');
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetCompanySettings());
            echo View::page('settings/company', [
                'settings' => $settings,
                'errors' => $e->errors(),
            ], activeNav: 'settings-firma', title: t('settings.company.title'));
        }
    }

    public function uploadLogo(Request $request): void
    {
        $file = $request->file('logo');

        if ($file !== null) {
            try {
                $this->commands->dispatch(new UploadCompanyLogo($file));
                Flash::success(t('settings.company.flash.logo_uploaded'));
            } catch (ValidationException $e) {
                Flash::error(implode(' ', $e->errors()));
            }
        }

        Response::redirect('/einstellungen/firma');
    }

    public function deleteLogo(Request $request): void
    {
        $this->commands->dispatch(new DeleteCompanyLogo());
        Flash::success(t('settings.company.flash.logo_removed'));
        Response::redirect('/einstellungen/firma');
    }

    /**
     * Liefert das hochgeladene Firmenlogo aus. Liegt wie Artikelbilder/RMA-
     * Dokumente bewusst außerhalb von public/, damit der Zugriff konsistent
     * über die Anwendung (inkl. Berechtigungsprüfung) läuft.
     */
    public function showLogo(Request $request): void
    {
        $settings = $this->queries->ask(new GetCompanySettings());

        if ($settings->logoFilename === null) {
            Response::notFound(t('settings.company.error.no_logo'));

            return;
        }

        $absolutePath = $this->fileStorage->absolutePath('company', $settings->logoFilename);
        $mimeType = function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: 'application/octet-stream') : 'application/octet-stream';

        Response::file($absolutePath, basename($settings->logoFilename), $mimeType);
    }

    public function uploadDarkLogo(Request $request): void
    {
        $file = $request->file('dark_logo');

        if ($file !== null) {
            try {
                $this->commands->dispatch(new UploadCompanyDarkLogo($file));
                Flash::success(t('settings.company.dark_logo.flash.uploaded'));
            } catch (ValidationException $e) {
                Flash::error(implode(' ', $e->errors()));
            }
        }

        Response::redirect('/einstellungen/firma');
    }

    public function deleteDarkLogo(Request $request): void
    {
        $this->commands->dispatch(new DeleteCompanyDarkLogo());
        Flash::success(t('settings.company.dark_logo.flash.removed'));
        Response::redirect('/einstellungen/firma');
    }

    /** Liefert das optionale Dunkelmodus-Logo aus, analog zu showLogo(). */
    public function showDarkLogo(Request $request): void
    {
        $settings = $this->queries->ask(new GetCompanySettings());

        if ($settings->darkLogoFilename === null) {
            Response::notFound(t('settings.company.error.no_logo'));

            return;
        }

        $absolutePath = $this->fileStorage->absolutePath('company', $settings->darkLogoFilename);
        $mimeType = function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: 'application/octet-stream') : 'application/octet-stream';

        Response::file($absolutePath, basename($settings->darkLogoFilename), $mimeType);
    }

    public function uploadBackgroundImage(Request $request): void
    {
        $file = $request->file('background_image');

        if ($file !== null) {
            try {
                $this->commands->dispatch(new UploadCompanyBackgroundImage($file));
                Flash::success(t('settings.company.background_image.flash.uploaded'));
            } catch (ValidationException $e) {
                Flash::error(implode(' ', $e->errors()));
            }
        }

        Response::redirect('/einstellungen/firma');
    }

    public function deleteBackgroundImage(Request $request): void
    {
        $this->commands->dispatch(new DeleteCompanyBackgroundImage());
        Flash::success(t('settings.company.background_image.flash.removed'));
        Response::redirect('/einstellungen/firma');
    }

    /** Liefert das optionale Hintergrundbild aus, analog zu showLogo()/showDarkLogo(). */
    public function showBackgroundImage(Request $request): void
    {
        $settings = $this->queries->ask(new GetCompanySettings());

        if ($settings->backgroundImageFilename === null) {
            Response::notFound(t('settings.company.error.no_background_image'));

            return;
        }

        $absolutePath = $this->fileStorage->absolutePath('company', $settings->backgroundImageFilename);
        $mimeType = function_exists('mime_content_type') ? (mime_content_type($absolutePath) ?: 'application/octet-stream') : 'application/octet-stream';

        Response::file($absolutePath, basename($settings->backgroundImageFilename), $mimeType);
    }
}
