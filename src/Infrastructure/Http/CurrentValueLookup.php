<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Insurance\CurrentValueRepositoryInterface;

/**
 * Zugriff der Views (Artikelformular, Inventarnummern) auf die festen
 * Artikel-Zeitwerte der Versicherungsliste (Migration 144), analog zu DryHireLookup –
 * wird in bootstrap.php gesetzt. Ohne Repository liefert alles null.
 */
final class CurrentValueLookup
{
    private static ?CurrentValueRepositoryInterface $repo = null;
    /** @var (callable(): bool)|null erst bei Bedarf abgefragt (liest die KI-Einstellungen aus der DB) */
    private static $aiAvailable = null;

    /** @param (callable(): bool)|null $aiAvailable */
    public static function set(?CurrentValueRepositoryInterface $repo, ?callable $aiAvailable = null): void
    {
        self::$repo = $repo;
        self::$aiAvailable = $aiAvailable;
    }

    public static function articleFixedValue(?string $articleId): ?string
    {
        return $articleId !== null && $articleId !== '' ? self::$repo?->articleFixedValue($articleId) : null;
    }

    public static function isAvailable(): bool
    {
        return self::$repo !== null;
    }

    public static function isAiAvailable(): bool
    {
        try {
            return self::$aiAvailable !== null && (self::$aiAvailable)();
        } catch (\Throwable) {
            return false;
        }
    }
}
