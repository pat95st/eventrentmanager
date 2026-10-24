<?php

declare(strict_types=1);

namespace App\Application\Receipt\Command;

use App\Domain\Exception\ValidationException;

/**
 * Gemeinsame Datei-Validierung für Create-/UpdateReceiptHandler UND den
 * Beleg-Posteingang-Import (ImportReceiptEmailsHandler filtert allerdings
 * bereits selbst über MimeMessageParser auf dieselben Typen). Erlaubt sind
 * PDF sowie JPG/PNG (Fotos eingescannter/fotografierter Belege) — anders
 * als bei RMA-Dokumenten (siehe AttachRmaDocumentHandler) aber KEIN E-Mail-
 * Anhang o. ä., da ein manuell hochgeladener Beleg fachlich immer eines
 * dieser drei Formate ist. Zusätzlich zum gemeldeten MIME-Type wird die
 * Dateisignatur (Magic Bytes) geprüft, um einer falschen/fehlenden
 * Content-Type-Angabe des Betriebssystems vorzubeugen.
 */
trait ValidatesReceiptFile
{
    private const MAX_SIZE_BYTES = 15 * 1024 * 1024; // 15 MB
    private const ALLOWED_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    /** @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file */
    private function assertValidReceiptFile(array $file): void
    {
        $mimeType = $this->detectReceiptFileMimeType($file);

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException(['document' => 'Nur PDF-, JPG- oder PNG-Dateien sind erlaubt.']);
        }

        if (is_file($file['tmp_name']) && !$this->matchesFileSignature($file['tmp_name'], $mimeType)) {
            throw new ValidationException(['document' => 'Die Datei entspricht nicht dem erkannten Dateityp (beschädigt oder falsch benannt?).']);
        }

        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['document' => 'Die Datei darf höchstens 15 MB groß sein.']);
        }
    }

    /** @param array{name: string, type: string, tmp_name: string} $file */
    private function detectReceiptFileMimeType(array $file): string
    {
        // Der vom Browser gemeldete Typ ist nicht vertrauenswürdig — wir prüfen
        // den tatsächlichen Dateiinhalt serverseitig nach (siehe AttachRmaDocumentHandler).
        if (is_file($file['tmp_name']) && function_exists('mime_content_type')) {
            $detected = mime_content_type($file['tmp_name']);
            if ($detected !== false) {
                return $detected;
            }
        }

        return $file['type'] !== '' ? $file['type'] : 'application/octet-stream';
    }

    /** Prüft die ersten Bytes der Datei gegen die für den erkannten MIME-Type üblichen Dateisignaturen ("Magic Bytes"). */
    private function matchesFileSignature(string $tmpPath, string $mimeType): bool
    {
        $handle = fopen($tmpPath, 'rb');
        if ($handle === false) {
            return true; // Datei konnte nicht geöffnet werden — Prüfung überspringen, statt fälschlich abzulehnen.
        }
        $header = fread($handle, 8);
        fclose($handle);

        if ($header === false) {
            return true;
        }

        return match ($mimeType) {
            'application/pdf' => str_starts_with($header, '%PDF'),
            'image/jpeg' => strlen($header) >= 3 && $header[0] === "\xFF" && $header[1] === "\xD8" && $header[2] === "\xFF",
            'image/png' => str_starts_with($header, "\x89PNG\r\n\x1a\n"),
            default => true,
        };
    }
}
