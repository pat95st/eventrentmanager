<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\UpdatePdfLayoutSettings;
use App\Application\Settings\Query\GetPdfLayoutSettings;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PdfLayout\PdfDocumentType;
use App\Domain\Settings\PdfLayout\PdfLayoutBlueprint;
use App\Infrastructure\Http\Pagination;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use function App\Infrastructure\Http\t;

/**
 * Visueller PDF-Vorlagen-Editor (ein Controller für alle sechs
 * Dokumenttypen, siehe PdfDocumentType) — der Dokumenttyp kommt als
 * Query-Parameter "?typ=rechnung" (Default: Rechnung), statt sechs
 * fast identische Controller/Views anzulegen. Analog zum bestehenden
 * Muster separater *PdfTemplateSettingsController für den reinen
 * Freitext-Teil (siehe InvoicePdfTemplateSettingsController), aber
 * bewusst EIN Controller, weil hier dieselbe Editor-Oberfläche nur mit
 * einem anderen Blueprint (siehe PdfLayoutBlueprint) befüllt wird.
 */
final class PdfLayoutSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
    ) {
    }

    public function edit(Request $request): void
    {
        $documentType = $this->documentTypeFromRequest($request);
        $settings = $this->queries->ask(new GetPdfLayoutSettings($documentType));
        $elements = PdfLayoutBlueprint::elementsFor($documentType);

        echo View::page('settings/pdf-layout-editor', [
            'documentType' => $documentType,
            'elements' => $elements,
            'settings' => $settings,
        ], activeNav: 'settings-pdf-layout', title: t('settings.pdf_layout.title'));
    }

    public function update(Request $request): void
    {
        $documentType = $this->documentTypeFromRequest($request);
        $positions = $request->post['positions'] ?? [];

        try {
            $this->commands->dispatch(new UpdatePdfLayoutSettings(
                documentType: $documentType,
                positions: is_array($positions) ? $positions : [],
            ));

            if (Pagination::isAjax($request)) {
                Response::json(['success' => true]);

                return;
            }

            Response::redirect('/einstellungen/pdf-layout?typ=' . $documentType->value);
        } catch (ValidationException $e) {
            if (Pagination::isAjax($request)) {
                Response::json(['errors' => $e->errors()], 422);

                return;
            }

            $settings = $this->queries->ask(new GetPdfLayoutSettings($documentType));
            echo View::page('settings/pdf-layout-editor', [
                'documentType' => $documentType,
                'elements' => PdfLayoutBlueprint::elementsFor($documentType),
                'settings' => $settings,
                'errors' => $e->errors(),
            ], activeNav: 'settings-pdf-layout', title: t('settings.pdf_layout.title'));
        }
    }

    private function documentTypeFromRequest(Request $request): PdfDocumentType
    {
        $raw = $request->query('typ') ?? $request->input('typ');

        return $raw !== null ? (PdfDocumentType::tryFrom($raw) ?? PdfDocumentType::Invoice) : PdfDocumentType::Invoice;
    }
}
