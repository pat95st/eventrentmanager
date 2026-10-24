<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdatePdfDesignSettings;
use App\Application\Settings\Query\GetPdfDesignSettings;
use App\Domain\Exception\ValidationException;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/**
 * Einstellungsseite für das gemeinsame Design-Grundgerüst von Angebots- und
 * Rechnungs-PDF (siehe App\Domain\Settings\PdfDesignSettings) — bewusst ein
 * einfaches Formular (Farbwähler + Auswahl links/rechts), KEIN
 * Canvas-/Drag-and-Drop-Editor (siehe Projektentscheidung im Klassen-Doc von
 * PdfDesignSettings).
 */
final class PdfDesignSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetPdfDesignSettings());

        echo View::page('settings/pdf-design', [
            'settings' => $settings,
            'errors' => [],
            'old' => [],
        ], activeNav: 'settings-pdf-design', title: t('settings.pdf_design.title'));
    }

    public function update(Request $request): void
    {
        $command = new UpdatePdfDesignSettings(
            accentColorHex: $request->input('accent_color', '') ?? '',
            logoPosition: $request->input('logo_position', '') ?? '',
        );

        try {
            $this->commands->dispatch($command);
            Flash::success(t('settings.pdf_design.flash.saved'));
            Response::redirect('/einstellungen/pdf-design');
        } catch (ValidationException $e) {
            $settings = $this->queries->ask(new GetPdfDesignSettings());
            echo View::page('settings/pdf-design', [
                'settings' => $settings,
                'errors' => $e->errors(),
                'old' => $request->post,
            ], activeNav: 'settings-pdf-design', title: t('settings.pdf_design.title'));
        }
    }
}
