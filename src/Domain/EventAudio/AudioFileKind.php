<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

/** Art einer hinterlegten Audiodatei einer Veranstaltung (Migration 135). */
enum AudioFileKind: string
{
    case Jingle = 'jingle';
    case Audio = 'audio';

    public function label(): string
    {
        return match ($this) {
            self::Jingle => 'Jingle',
            self::Audio => 'Audio-Datei',
        };
    }
}
