<?php

declare(strict_types=1);

namespace App\Infrastructure\Branding;

/**
 * Erzeugt aus dem hochgeladenen Favicon (Einstellungen › Firma) die Icons der
 * installierten App (PWA): 192/512 px für das Manifest, eine „maskable“
 * Variante mit Schutzrand für Android und 180 px als Apple-Touch-Icon.
 *
 * Quellen: PNG direkt, ICO nur, wenn es ein PNG-Bild enthält (das ist bei
 * großen modernen ICO-Dateien üblich), SVG wird im Manifest direkt verwendet
 * (sizes "any"); für das Apple-Icon braucht es ein Rasterbild, dort bleibt
 * bei SVG das Standard-Icon. Zu kleine Bilder (< 144 px) werden nicht
 * verwendet, weil sie auf dem Startbildschirm unscharf wären.
 */
final class AppIconRenderer
{
    public const MIN_SOURCE_SIZE = 144;

    /** Rasterquelle als PNG-Bytes oder null (kein/zu kleines/ungeeignetes Favicon). */
    public function rasterSource(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $data = (string) file_get_contents($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $png = match ($extension) {
            'png' => $data,
            'ico' => $this->pngFromIco($data),
            default => null,
        };
        if ($png === null) {
            return null;
        }
        $size = @getimagesizefromstring($png);
        if ($size === false || min($size[0], $size[1]) < self::MIN_SOURCE_SIZE) {
            return null;
        }

        return $png;
    }

    public function isSvg(string $path): bool
    {
        return is_file($path) && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg';
    }

    /**
     * Quadratisches PNG in der gewünschten Größe. $padded: Hintergrund weiß und
     * Motiv auf 80 % verkleinert (maskable/Apple, dort wird beschnitten bzw.
     * Transparenz schwarz dargestellt).
     */
    public function render(string $png, int $size, bool $padded): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }
        $source = @imagecreatefromstring($png);
        if ($source === false) {
            return null;
        }
        $canvas = imagecreatetruecolor($size, $size);
        imagesavealpha($canvas, true);
        imagealphablending($canvas, false);
        $background = $padded ? imagecolorallocatealpha($canvas, 255, 255, 255, 0) : imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $background);
        imagealphablending($canvas, true);

        $width = imagesx($source);
        $height = imagesy($source);
        $box = $padded ? (int) round($size * 0.8) : $size;
        $scale = min($box / $width, $box / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        imagecopyresampled($canvas, $source, (int) (($size - $targetWidth) / 2), (int) (($size - $targetHeight) / 2), 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        ob_start();
        imagepng($canvas, null, 9);
        $out = ob_get_clean();
        imagedestroy($canvas);

        return $out === false || $out === '' ? null : $out;
    }

    /** Größtes PNG-Bild aus einer ICO-Datei (BMP-Einträge werden übersprungen). */
    private function pngFromIco(string $data): ?string
    {
        if (strlen($data) < 6) {
            return null;
        }
        $header = unpack('vreserved/vtype/vcount', substr($data, 0, 6));
        if ($header === false || $header['type'] !== 1) {
            return null;
        }
        $best = null;
        $bestSize = 0;
        for ($i = 0; $i < $header['count']; $i++) {
            $entry = substr($data, 6 + 16 * $i, 16);
            if (strlen($entry) < 16) {
                break;
            }
            $e = unpack('Cwidth/Cheight/Ccolors/Creserved/vplanes/vbits/Vsize/Voffset', $entry);
            $image = substr($data, $e['offset'], $e['size']);
            if (!str_starts_with($image, "\x89PNG")) {
                continue;
            }
            $dimensions = @getimagesizefromstring($image);
            $size = $dimensions !== false ? $dimensions[0] : ($e['width'] === 0 ? 256 : $e['width']);
            if ($size > $bestSize) {
                $best = $image;
                $bestSize = $size;
            }
        }

        return $best;
    }
}
