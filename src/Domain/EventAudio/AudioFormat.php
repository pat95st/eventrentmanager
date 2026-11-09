<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

/**
 * Erlaubte Audioformate. Hochgeladene Jingles/Audio-Dateien: WAV, FLAC,
 * AAC (auch im M4A-Container) und MP3 (Nutzerwunsch). Mikrofon-Aufnahmen aus
 * dem Browser kommen je nach Gerät als WebM/Opus (Chrome, Firefox, Android)
 * oder MP4/AAC (Safari, iOS) — beides spielt der Raspberry Pi mit
 * ffmpeg/mpv ab.
 */
final class AudioFormat
{
    /** Endung => kanonischer MIME-Typ für die Auslieferung. */
    public const UPLOAD_EXTENSIONS = [
        'wav' => 'audio/wav',
        'flac' => 'audio/flac',
        'aac' => 'audio/aac',
        'm4a' => 'audio/mp4',
        'mp3' => 'audio/mpeg',
    ];

    public const RECORDING_MIME_EXTENSIONS = [
        'audio/webm' => 'webm',
        'audio/ogg' => 'ogg',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'audio/mpeg' => 'mp3',
        'audio/wav' => 'wav',
        'audio/x-wav' => 'wav',
        'video/webm' => 'webm', // Chrome meldet reine Audio-Aufnahmen teils so
        'video/mp4' => 'm4a',
    ];

    public static function uploadMimeFor(string $filename): ?string
    {
        return self::UPLOAD_EXTENSIONS[strtolower(pathinfo($filename, PATHINFO_EXTENSION))] ?? null;
    }

    /**
     * Prüft grob den Dateiinhalt (Magic Bytes), damit nicht beliebige
     * Dateien mit Audio-Endung gespeichert werden.
     */
    public static function looksLikeAudio(string $path, string $extension): bool
    {
        $head = @file_get_contents($path, false, null, 0, 16);
        if ($head === false || strlen($head) < 4) {
            return false;
        }

        return match (strtolower($extension)) {
            'wav' => str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WAVE',
            'flac' => str_starts_with($head, 'fLaC'),
            'mp3' => str_starts_with($head, 'ID3') || (ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0),
            'aac' => (ord($head[0]) === 0xFF && (ord($head[1]) & 0xF6) === 0xF0) || str_starts_with($head, 'ID3') || substr($head, 4, 4) === 'ftyp',
            'm4a', 'mp4' => substr($head, 4, 4) === 'ftyp',
            'webm' => str_starts_with($head, "\x1A\x45\xDF\xA3"),
            'ogg' => str_starts_with($head, 'OggS'),
            default => false,
        };
    }

    public static function acceptAttribute(): string
    {
        return '.wav,.flac,.aac,.m4a,.mp3,audio/wav,audio/flac,audio/aac,audio/mp4,audio/mpeg';
    }
}
