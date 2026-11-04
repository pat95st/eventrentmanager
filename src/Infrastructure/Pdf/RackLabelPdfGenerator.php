<?php

declare(strict_types=1);

namespace App\Infrastructure\Pdf;

use App\Application\Rack\Query\RackDetail;
use App\Application\Rack\Query\RackExemplarDetail;
use App\Domain\Settings\CompanySettings;
use App\Infrastructure\FileStorage\LocalFileStorage;

/**
 * Beschriftungen für ein Rack-Exemplar (Nutzerwunsch, Migration 124), jeweils
 * mit Firmenlogo:
 * - FRONT / REAR: für den vorderen bzw. hinteren Deckel, A6 quer – Rack-
 *   Nummer, großer Aufdruck „FRONT“ bzw. „REAR“ und der Inhalt.
 * - TOP: Inhaltsliste für oben auf dem Rack, A5 hoch – alle Einbauten mit
 *   HE-Position, Inventurnummer und fest zugeordnetem Gerät.
 */
final class RackLabelPdfGenerator
{
    public const FRONT = 'front';
    public const REAR = 'rear';
    public const TOP = 'top';

    private const A6_LANDSCAPE = [419.53, 297.64];
    private const A5_PORTRAIT = [419.53, 595.28];

    public function __construct(private readonly LocalFileStorage $fileStorage)
    {
    }

    public function generate(RackDetail $detail, RackExemplarDetail $entry, string $kind, CompanySettings $companySettings): string
    {
        $logo = $this->prepareLogoJpeg($companySettings);

        return $kind === self::TOP
            ? $this->contentsLabel($detail, $entry, $logo)
            : $this->lidLabel($detail, $entry, $kind === self::REAR ? 'REAR' : 'FRONT', $logo);
    }

    private function lidLabel(RackDetail $detail, RackExemplarDetail $entry, string $side, ?string $logo): string
    {
        [$width, $height] = self::A6_LANDSCAPE;
        $writer = new SimplePdfWriter($width, $height);
        $writer->addPage();
        $margin = 18.0;
        $exemplar = $entry->exemplar;

        // Kopf: Logo links, Rack-Nummer rechts
        $top = $height - $margin;
        if ($logo !== null) {
            [$w, $h] = $this->fit($logo, 120, 38);
            $writer->image($logo, $margin, $top - $h, $w, $h);
        }
        $writer->textRight($width - $margin, $top - 20, $exemplar->rackNumber, 22, true);
        $writer->textRight($width - $margin, $top - 33, $detail->rack->name . ' · ' . $exemplar->inventoryNumber, 7.5, false, false, '0.3 0.3 0.3');
        $writer->line($margin, $top - 44, $width - $margin, $top - 44, 1.2, '0 0 0');

        // Großer Aufdruck FRONT / REAR
        $size = 64.0;
        while ($size > 30 && $writer->textWidth($side, $size, true) > $width - 2 * $margin) {
            $size -= 2;
        }
        $writer->textInRole(SimplePdfWriter::ROLE_HEADING, ($width - $writer->textWidth($side, $size, true)) / 2, $top - 44 - 14 - $size * 0.72, $side, $size, true);
        $y = $top - 44 - 14 - $size * 0.72 - 20;

        // Inhalt (kompakt)
        $writer->line($margin, $y + 8, $width - $margin, $y + 8, 0.5, '0.6 0.6 0.6');
        $lines = $this->contentLines($entry);
        $available = $y - $margin;
        // Bei wenigen Geräten größer (aus der Entfernung lesbar), bei vielen kleiner.
        $lineHeight = max(8.0, min(15.0, $available / max(1, count($lines))));
        $fontSize = $lineHeight * 0.75;
        $maxLines = max(1, (int) floor($available / $lineHeight));
        if (count($lines) > $maxLines) {
            $rest = count($lines) - ($maxLines - 1);
            $lines = array_slice($lines, 0, $maxLines - 1);
            $lines[] = ['', '+ ' . $rest . ' weitere Geräte – siehe Inhaltsliste oben auf dem Rack', ''];
        }
        if ($lines === []) {
            $writer->text($margin, $y - $lineHeight + 2, 'Keine Geräte eingebaut.', $fontSize, false, false, '0.4 0.4 0.4');
        }
        foreach ($lines as [$units, $label, $number]) {
            $y -= $lineHeight;
            $writer->text($margin, $y, $units, $fontSize, true);
            $writer->text($margin + $fontSize * 4.5, $y, $this->truncate($writer, $label, $fontSize, $width - 2 * $margin - $fontSize * 4.5 - $fontSize * 10), $fontSize);
            $writer->textRight($width - $margin, $y, $number, $fontSize, false, false, '0.3 0.3 0.3');
        }

        return $writer->output();
    }

    private function contentsLabel(RackDetail $detail, RackExemplarDetail $entry, ?string $logo): string
    {
        [$width, $height] = self::A5_PORTRAIT;
        $writer = new SimplePdfWriter($width, $height);
        $writer->addPage();
        $margin = 30.0;
        $exemplar = $entry->exemplar;
        $rack = $detail->rack;

        $top = $height - $margin;
        if ($logo !== null) {
            [$w, $h] = $this->fit($logo, 140, 48);
            $writer->image($logo, $width - $margin - $w, $top - $h, $w, $h);
        }
        $writer->textInRole(SimplePdfWriter::ROLE_HEADING, $margin, $top - 24, $exemplar->rackNumber, 28, true);
        $writer->text($margin, $top - 42, $rack->name, 11, true);
        $writer->text($margin, $top - 56, 'Inventurnummer: ' . $exemplar->inventoryNumber, 9, false, false, '0.3 0.3 0.3');
        $info = $rack->heightUnits . ' HE · ' . $rack->width->label() . ($rack->installDepthCm !== null ? ' · Einbautiefe ' . rtrim(rtrim(number_format((float) $rack->installDepthCm, 1, ',', ''), '0'), ',') . ' cm' : '');
        $writer->text($margin, $top - 68, $info, 9, false, false, '0.3 0.3 0.3');
        $y = $top - 84;
        $writer->line($margin, $y, $width - $margin, $y, 1.2, '0 0 0');
        $y -= 22;
        $writer->textInRole(SimplePdfWriter::ROLE_HEADING, $margin, $y, 'Inhalt', 14, true);
        $y -= 12;

        // Tabelle: HE | Gerät | Inventurnummer
        $writer->filledRect($margin, $y - 14, $width - 2 * $margin, 18, '0.9 0.9 0.9');
        $writer->text($margin + 4, $y - 9, 'HE', 9, true);
        $writer->text($margin + 50, $y - 9, 'Gerät', 9, true);
        $writer->textRight($width - $margin - 4, $y - 9, 'Inventurnummer', 9, true);
        $y -= 20;

        $lines = $this->contentLines($entry);
        $fontSize = 10.0;
        $lineHeight = 17.0;
        $bottom = $margin + 40;
        if (count($lines) * $lineHeight > $y - $bottom) {
            $lineHeight = max(10.0, ($y - $bottom) / max(1, count($lines)));
            $fontSize = min(10.0, $lineHeight - 3);
        }
        if ($lines === []) {
            $writer->text($margin + 4, $y - 12, 'Keine Geräte eingebaut.', 10, false, false, '0.4 0.4 0.4');
        }
        foreach ($lines as [$units, $label, $number]) {
            if ($y - $lineHeight < $bottom) {
                $writer->text($margin + 4, $y - $lineHeight + 4, '… weitere Geräte siehe App', $fontSize, false, true);
                break;
            }
            $y -= $lineHeight;
            $writer->text($margin + 4, $y + 4, $units, $fontSize, true);
            $writer->text($margin + 50, $y + 4, $this->truncate($writer, $label, $fontSize, $width - 2 * $margin - 50 - 105), $fontSize);
            $writer->textRight($width - $margin - 4, $y + 4, $number, $fontSize * 0.9, false, false, '0.3 0.3 0.3');
            $writer->line($margin, $y, $width - $margin, $y, 0.3, '0.8 0.8 0.8');
        }

        // Fuß: fest zugeordnetes Gerät
        $footer = $entry->fixedDevice !== null
            ? 'Fest zugeordnet: ' . $entry->fixedDevice['label'] . ' (' . $entry->fixedDevice['number'] . ') – das Rack geht mit diesem Gerät mit.'
            : 'Frei: ' . $entry->freeUnits() . ' von ' . $rack->heightUnits . ' HE' . ($entry->halfFreeUnits() > 0 ? ' (+ ' . $entry->halfFreeUnits() . ' halbe für 9,5 Zoll)' : '');
        $writer->line($margin, $margin + 22, $width - $margin, $margin + 22, 0.5, '0.6 0.6 0.6');
        $writer->text($margin, $margin + 8, $this->truncate($writer, $footer, 8.5, $width - 2 * $margin), 8.5, false, false, '0.25 0.25 0.25');

        return $writer->output();
    }

    /** @return list<array{0: string, 1: string, 2: string}> [HE, Gerät, Inventurnummer] von oben nach unten */
    private function contentLines(RackExemplarDetail $entry): array
    {
        $lines = [];
        foreach ($entry->slots as $slot) {
            $units = $slot['startUnit'] === $slot['endUnit'] ? (string) $slot['startUnit'] : $slot['startUnit'] . '–' . $slot['endUnit'];
            // 9,5-Zoll-Gerät im 19-Zoll-Rack (Migration 128): Seite mit angeben.
            $side = $slot['side'] ?? null;
            if ($side !== null) {
                $units .= $side === 'left' ? ' L' : ' R';
            }
            $lines[] = [$units, $slot['label'], $slot['number']];
        }

        return $lines;
    }

    private function truncate(SimplePdfWriter $writer, string $text, float $fontSize, float $maxWidth): string
    {
        if ($writer->textWidth($text, $fontSize) <= $maxWidth) {
            return $text;
        }
        while ($text !== '' && $writer->textWidth($text . '…', $fontSize) > $maxWidth) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text) . '…';
    }

    private function prepareLogoJpeg(CompanySettings $companySettings): ?string
    {
        if ($companySettings->logoFilename === null || !extension_loaded('gd')) {
            return null;
        }
        $path = $this->fileStorage->absolutePath('company', $companySettings->logoFilename);
        $data = is_file($path) ? (string) @file_get_contents($path) : '';
        $source = $data !== '' ? @imagecreatefromstring($data) : false;
        if ($source === false) {
            return null;
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $canvas = imagecreatetruecolor($width, $height);
        imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);
        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = ob_get_clean();
        imagedestroy($canvas);

        return $jpeg === false || $jpeg === '' ? null : $jpeg;
    }

    /** @return array{0: float, 1: float} */
    private function fit(string $jpeg, float $maxWidth, float $maxHeight): array
    {
        $size = @getimagesizefromstring($jpeg);
        if ($size === false || $size[0] <= 0 || $size[1] <= 0) {
            return [$maxWidth, $maxHeight];
        }
        $scale = min($maxWidth / $size[0], $maxHeight / $size[1]);

        return [$size[0] * $scale, $size[1] * $scale];
    }
}
