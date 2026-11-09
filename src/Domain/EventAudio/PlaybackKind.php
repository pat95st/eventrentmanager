<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

/** Art eines Abspielauftrags an das Abspielgerät. */
enum PlaybackKind: string
{
    case Jingle = 'jingle';
    case Audio = 'audio';
    case Tts = 'tts';          // eingetippte Durchsage, Sprachausgabe auf dem Pi
    case Recording = 'recording'; // per Mikrofon aufgenommene Durchsage
    case Stop = 'stop';        // laufende Wiedergabe sofort beenden

    public function label(): string
    {
        return match ($this) {
            self::Jingle => 'Jingle',
            self::Audio => 'Audio-Datei',
            self::Tts => 'Durchsage (Text)',
            self::Recording => 'Durchsage (Aufnahme)',
            self::Stop => 'Stopp',
        };
    }

    public function isAnnouncement(): bool
    {
        return $this === self::Tts || $this === self::Recording;
    }
}
