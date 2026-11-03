<?php

declare(strict_types=1);

namespace App\Infrastructure\Fonts;

use App\Domain\Exception\ValidationException;
use App\Infrastructure\Pdf\TrueTypeFont;

/**
 * Lädt eine Schriftfamilie einmalig von Google Fonts (Migration 116).
 *
 * Die CSS-API (fonts.googleapis.com/css2) liefert Clients, die kein WOFF
 * kennen, Links auf TrueType-Dateien (fonts.gstatic.com/….ttf) — genau das
 * Format, das SimplePdfWriter einbetten kann. Je Schnitt (normal, fett,
 * kursiv, fett kursiv) wird einzeln angefragt, weil nicht jede Familie alle
 * Schnitte hat. Die PDFs selbst greifen nie auf Google zu.
 */
class GoogleFontsDownloader
{
    /** Schnitt => ital,wght-Angabe der CSS-API */
    private const STYLES = ['regular' => '0,400', 'bold' => '0,700', 'italic' => '1,400', 'boldItalic' => '1,700'];

    /** Beliebte Familien als Vorschlagsliste im Formular (jede andere Google-Fonts-Familie geht auch). */
    public const SUGGESTIONS = [
        'Archivo', 'Arimo', 'Assistant', 'Barlow', 'Barlow Condensed', 'Bebas Neue', 'Cabin', 'Cormorant Garamond',
        'Crimson Pro', 'DM Sans', 'DM Serif Display', 'EB Garamond', 'Exo 2', 'Fira Sans', 'Heebo', 'Hind',
        'IBM Plex Sans', 'IBM Plex Serif', 'IBM Plex Mono', 'Inter', 'Josefin Sans', 'Kanit', 'Karla', 'Lato',
        'Lexend', 'Libre Baskerville', 'Lora', 'Manrope', 'Merriweather', 'Montserrat', 'Mulish', 'Noto Sans',
        'Noto Serif', 'Nunito', 'Nunito Sans', 'Open Sans', 'Oswald', 'Outfit', 'Playfair Display', 'Plus Jakarta Sans',
        'Poppins', 'PT Sans', 'PT Serif', 'Quicksand', 'Raleway', 'Red Hat Display', 'Roboto', 'Roboto Condensed',
        'Roboto Mono', 'Roboto Slab', 'Rubik', 'Source Sans 3', 'Source Serif 4', 'Space Grotesk', 'Tinos',
        'Titillium Web', 'Ubuntu', 'Work Sans',
    ];

    /**
     * @return array<string, string> Schnitt => TTF-Bytes (mindestens "regular")
     * @throws ValidationException wenn die Familie nicht geladen werden kann
     */
    public function download(string $family): array
    {
        $family = trim($family);
        if ($family === '' || preg_match('/^[\p{L}\p{N} ]{2,100}$/u', $family) !== 1) {
            throw new ValidationException(['family' => 'Bitte den Namen einer Google-Fonts-Schrift angeben (nur Buchstaben, Ziffern und Leerzeichen).']);
        }

        $files = [];
        $lastError = null;
        foreach (self::STYLES as $style => $spec) {
            try {
                $bytes = $this->fetchStyle($family, $spec);
            } catch (\RuntimeException $e) {
                $lastError = $e->getMessage();
                $bytes = null;
            }
            if ($bytes !== null) {
                $files[$style] = $bytes;
            }
        }

        if (!isset($files['regular'])) {
            // Manche Familien haben kein Gewicht 400 (z. B. nur "Light"): Standardschnitt anfragen.
            try {
                $bytes = $this->fetchCss($this->cssUrl($family, null));
                if ($bytes !== null) {
                    $files['regular'] = $bytes;
                }
            } catch (\RuntimeException $e) {
                $lastError = $e->getMessage();
            }
        }

        if (!isset($files['regular'])) {
            throw new ValidationException(['family' => $lastError ?? "Die Schrift „{$family}“ wurde bei Google Fonts nicht gefunden. Bitte die Schreibweise prüfen (z. B. „Open Sans“)."]);
        }

        return $files;
    }

    private function fetchStyle(string $family, string $spec): ?string
    {
        return $this->fetchCss($this->cssUrl($family, $spec));
    }

    private function cssUrl(string $family, ?string $spec): string
    {
        $url = 'https://fonts.googleapis.com/css2?family=' . str_replace('%20', '+', rawurlencode($family));
        if ($spec !== null) {
            $url .= ':ital,wght@' . $spec;
        }

        return $url;
    }

    /** Lädt das CSS und daraus die erste TTF-Datei; null, wenn es den Schnitt nicht gibt. */
    private function fetchCss(string $url): ?string
    {
        [$status, $css] = $this->get($url);
        if ($status === 400 || $status === 404) {
            return null; // Schnitt oder Familie existiert nicht
        }
        if ($status !== 200 || $css === '') {
            throw new \RuntimeException("Google Fonts antwortet nicht wie erwartet (HTTP {$status}). Ist der Server mit dem Internet verbunden? Alternativ die TTF-Dateien hochladen.");
        }
        if (preg_match('~url\((https://fonts\.gstatic\.com/[^)\s]+?\.ttf)\)~', $css, $m) !== 1) {
            throw new \RuntimeException('Google Fonts hat keine TrueType-Datei geliefert. Bitte die TTF-Dateien stattdessen hochladen.');
        }

        [$fileStatus, $bytes] = $this->get($m[1]);
        if ($fileStatus !== 200 || $bytes === '') {
            throw new \RuntimeException("Die Schriftdatei konnte nicht geladen werden (HTTP {$fileStatus}).");
        }
        TrueTypeFont::fromBytes($bytes); // wirft bei ungültiger Datei

        return $bytes;
    }

    /** @return array{0: int, 1: string} HTTP-Status und Inhalt */
    protected function get(string $url): array
    {
        // Bewusst einfacher User-Agent ohne WOFF-Unterstützung, damit Google TTF liefert.
        $userAgent = 'Materialverleih-PDF-Fonts/1.0';

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_USERAGENT => $userAgent,
            ]);
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            if ($body === false) {
                throw new \RuntimeException('Verbindung zu Google Fonts fehlgeschlagen: ' . $error . '. Alternativ die TTF-Dateien hochladen.');
            }

            return [$status, (string) $body];
        }

        $context = stream_context_create(['http' => ['timeout' => 20, 'user_agent' => $userAgent, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new \RuntimeException('Verbindung zu Google Fonts fehlgeschlagen. Alternativ die TTF-Dateien hochladen.');
        }
        $status = 0;
        foreach ($http_response_header ?? [] as $header) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $header, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        return [$status, $body];
    }
}
