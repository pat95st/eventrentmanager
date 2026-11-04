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
            phone: $request->input('phone') ?? '',
            email: $request->input('email') ?? '',
            website: $request->input('website') ?? '',
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

        Response::cachedFile($absolutePath, basename($settings->logoFilename), $mimeType, $request->query('v') === \App\Infrastructure\Http\CompanyBranding::version($settings->logoFilename));
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

        Response::cachedFile($absolutePath, basename($settings->darkLogoFilename), $mimeType, $request->query('v') === \App\Infrastructure\Http\CompanyBranding::version($settings->darkLogoFilename));
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

        Response::cachedFile($absolutePath, basename($settings->backgroundImageFilename), $mimeType, $request->query('v') === \App\Infrastructure\Http\CompanyBranding::version($settings->backgroundImageFilename));
    }

    public function uploadFavicon(Request $request): void
    {
        $file = $request->file('favicon');

        if ($file !== null) {
            try {
                $this->commands->dispatch(new \App\Application\Settings\Command\UploadCompanyFavicon($file));
                Flash::success(t('settings.company.favicon.flash.uploaded'));
            } catch (ValidationException $e) {
                Flash::error(implode(' ', $e->errors()));
            }
        }

        Response::redirect('/einstellungen/firma');
    }

    public function deleteFavicon(Request $request): void
    {
        $this->commands->dispatch(new \App\Application\Settings\Command\DeleteCompanyFavicon());
        Flash::success(t('settings.company.favicon.flash.removed'));
        Response::redirect('/einstellungen/firma');
    }

    /** Liefert das eigene Favicon aus (öffentlich, auch für die Login-Seite), sonst das Standard-Favicon. */
    /**
     * Manifest der installierten App mit dem Favicon als App-Icon (sofern
     * geeignet, siehe AppIconRenderer); Grundlage ist public/manifest.webmanifest.
     */
    public function appManifest(Request $request): void
    {
        $manifest = json_decode((string) file_get_contents(\App\Shared\ProjectPaths::rootDirectory() . '/public/manifest.webmanifest'), true) ?: [];
        $settings = $this->queries->ask(new GetCompanySettings());
        if ($settings->faviconFilename !== null) {
            $path = $this->fileStorage->absolutePath('company', $settings->faviconFilename);
            $renderer = new \App\Infrastructure\Branding\AppIconRenderer();
            $v = \App\Infrastructure\Http\CompanyBranding::version($settings->faviconFilename);
            if ($renderer->rasterSource($path) !== null) {
                $manifest['icons'] = [
                    ['src' => '/app-icon/192?v=' . $v, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => '/app-icon/512?v=' . $v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                    ['src' => '/app-icon/maskable?v=' . $v, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
                ];
            } elseif ($renderer->isSvg($path)) {
                array_unshift($manifest['icons'], ['src' => '/firmen-favicon?v=' . $v, 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any']);
            }
        }
        header_remove('Pragma');
        Response::raw(
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            200,
            'application/manifest+json',
            ['Cache-Control' => 'private, no-cache'],
        );
    }

    /** App-Icon aus dem Favicon: /app-icon/192, /512, /maskable, /apple. */
    public function appIcon(Request $request): void
    {
        $variant = $request->param('variant');
        [$size, $padded, $fallback] = match ($variant) {
            '192' => [192, false, 'icon-192.png'],
            'maskable' => [512, true, 'icon-maskable-512.png'],
            'apple' => [180, true, 'apple-touch-icon.png'],
            default => [512, false, 'icon-512.png'],
        };
        $settings = $this->queries->ask(new GetCompanySettings());
        $renderer = new \App\Infrastructure\Branding\AppIconRenderer();
        $source = $settings->faviconFilename !== null ? $renderer->rasterSource($this->fileStorage->absolutePath('company', $settings->faviconFilename)) : null;
        $png = $source !== null ? $renderer->render($source, $size, $padded) : null;
        if ($png === null) {
            Response::redirect('/assets/img/icons/' . $fallback);

            return;
        }
        $immutable = $request->query('v') === \App\Infrastructure\Http\CompanyBranding::version((string) $settings->faviconFilename);
        header_remove('Pragma');
        header_remove('Expires');
        Response::raw($png, 200, 'image/png', ['Cache-Control' => $immutable ? 'private, max-age=31536000, immutable' : 'private, no-cache']);
    }

    public function showFavicon(Request $request): void
    {
        $settings = $this->queries->ask(new GetCompanySettings());

        if ($settings->faviconFilename === null) {
            Response::redirect('/favicon.ico');

            return;
        }

        $absolutePath = $this->fileStorage->absolutePath('company', $settings->faviconFilename);
        $mimeType = match (strtolower(pathinfo($settings->faviconFilename, PATHINFO_EXTENSION))) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            default => 'image/x-icon',
        };

        Response::cachedFile($absolutePath, basename($settings->faviconFilename), $mimeType, $request->query('v') === \App\Infrastructure\Http\CompanyBranding::version($settings->faviconFilename));
    }
}
