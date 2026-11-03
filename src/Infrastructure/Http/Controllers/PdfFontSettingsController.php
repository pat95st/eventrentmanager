<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Command\CommandBus;
use App\Application\Query\QueryBus;
use App\Application\Settings\Command\AddGoogleFont;
use App\Application\Settings\Command\DeletePdfFont;
use App\Application\Settings\Command\UpdatePdfFontAssignments;
use App\Application\Settings\Command\UploadPdfFont;
use App\Application\Settings\Query\GetPdfFontSettings;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\PdfFont;
use App\Domain\Settings\PdfFontRole;
use App\Infrastructure\FileStorage\LocalFileStorage;
use App\Infrastructure\Http\Flash;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;
use App\Infrastructure\Pdf\SimplePdfWriter;

/** Einstellungen › Schriftarten (Migration 116). */
final class PdfFontSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function edit(Request $request): void
    {
        $settings = $this->queries->ask(new GetPdfFontSettings());
        echo View::page('settings/pdf-fonts', [
            'fonts' => $settings['fonts'],
            'assignments' => $settings['assignments'],
            'suggestions' => \App\Infrastructure\Fonts\GoogleFontsDownloader::SUGGESTIONS,
        ], activeNav: 'settings-schriften', title: 'Schriftarten');
    }

    public function addGoogle(Request $request): void
    {
        $family = $request->input('family', '') ?? '';
        try {
            $this->commands->dispatch(new AddGoogleFont($family));
            Flash::success('Die Schrift „' . trim($family) . '“ wurde von Google Fonts geladen.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect('/einstellungen/schriften');
    }

    public function upload(Request $request): void
    {
        $files = [];
        foreach (PdfFont::STYLES as $style) {
            $files[$style] = $request->file('file_' . $style);
        }
        try {
            $this->commands->dispatch(new UploadPdfFont($request->input('family', '') ?? '', $files));
            Flash::success('Die Schrift wurde hochgeladen.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        } catch (ConflictException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect('/einstellungen/schriften');
    }

    public function delete(Request $request): void
    {
        try {
            $this->commands->dispatch(new DeletePdfFont($request->param('id')));
            Flash::success('Die Schrift wurde entfernt.');
        } catch (NotFoundException $e) {
            Flash::error($e->getMessage());
        }
        Response::redirect('/einstellungen/schriften');
    }

    public function assign(Request $request): void
    {
        $assignments = [];
        foreach (PdfFontRole::cases() as $role) {
            $assignments[$role->value] = $request->input('role_' . $role->value);
        }
        try {
            $this->commands->dispatch(new UpdatePdfFontAssignments($assignments));
            Flash::success('Die Schriftzuordnung wurde gespeichert. Sie gilt für alle neu erzeugten PDFs.');
        } catch (ValidationException $e) {
            Flash::error(implode(' ', $e->errors()));
        }
        Response::redirect('/einstellungen/schriften');
    }

    /** Schriftdatei für die Vorschau im Browser (@font-face), nur für angemeldete Administratoren. */
    public function file(Request $request): void
    {
        $settings = $this->queries->ask(new GetPdfFontSettings());
        $style = (string) $request->query('style', 'regular');
        foreach ($settings['fonts'] as $font) {
            if ($font->id === $request->param('id')) {
                $file = $font->files()[$style] ?? null;
                if ($file !== null && is_file($path = $this->fileStorage->absolutePath('fonts', $file))) {
                    header('Cache-Control: private, max-age=86400');
                    Response::file($path, basename($file), 'font/ttf');

                    return;
                }
            }
        }
        Response::notFound('Schriftdatei wurde nicht gefunden.');
    }

    /** Muster-PDF mit allen Bereichen in der aktuell gespeicherten Zuordnung. */
    public function preview(Request $request): void
    {
        $writer = new SimplePdfWriter();
        $writer->addPage();
        $width = $writer->pageWidth();
        $y = $writer->pageHeight() - 60;
        $margin = 50.0;

        $writer->textInRole(SimplePdfWriter::ROLE_HEADING, $margin, $y, 'Angebot (Überschrift)', 20, true);
        $y -= 30;
        $writer->text($margin, $y, 'Sehr geehrte Frau Musterfrau,', 10);
        $y -= 15;
        $writer->text($margin, $y, 'gerne bieten wir Ihnen folgende Artikel an. Dies ist der Fließtext (Bereich „Text“).', 10);
        $y -= 15;
        $writer->text($margin, $y, 'Fett, ', 10, true);
        $writer->text($margin + $writer->textWidth('Fett, ', 10, true), $y, 'kursiv und ÄÖÜ äöü ß € – „Anführung“', 10, false, true);
        $y -= 30;

        $writer->textInRole(SimplePdfWriter::ROLE_HEADING, $margin, $y, 'Abschnittsüberschrift', 12, true);
        $y -= 20;

        $previous = $writer->setFontRole(SimplePdfWriter::ROLE_TABLE);
        $writer->filledRect($margin, $y - 5, $width - 2 * $margin, 18, '0.92 0.92 0.92');
        $writer->text($margin + 4, $y, 'Artikel', 9, true);
        $writer->textRight($width - $margin - 4, $y, 'Summe', 9, true);
        $y -= 20;
        foreach ([['Line-Array-Topteil 12"', '1.249,00 €'], ['Funkmikrofon-Set', '89,50 €'], ['XLR-Kabel 10 m (Zubehör)', '12,00 €']] as [$name, $amount]) {
            $writer->text($margin + 4, $y, $name, 9);
            $writer->textRight($width - $margin - 4, $y, $amount, 9);
            $y -= 15;
        }
        $writer->line($margin, $y + 5, $width - $margin, $y + 5);
        $y -= 5;
        $writer->text($width - $margin - 200, $y, 'Gesamtsumme:', 10, true);
        $writer->textRight($width - $margin - 4, $y, '1.350,50 €', 10, true);
        $writer->setFontRole($previous);

        $writer->setFontRole(SimplePdfWriter::ROLE_FOOTER);
        $writer->line($margin, 70, $width - $margin, 70, 0.5, '0.45 0.45 0.45');
        $writer->text($margin, 56, 'Musterfirma GmbH · Musterstraße 1 · 12345 Musterstadt (Bereich „Fußzeile“)', 7.5, color: '0.25 0.25 0.25');
        $writer->text($margin, 46, 'IBAN DE00 1234 5678 9012 3456 78 · BIC MUSTDEFFXXX', 7.5, color: '0.25 0.25 0.25');

        Response::raw($writer->output(), 200, 'application/pdf', ['Content-Disposition' => 'inline; filename="Schriftvorschau.pdf"']);
    }
}
