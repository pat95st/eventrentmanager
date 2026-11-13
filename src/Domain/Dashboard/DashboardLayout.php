<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

/**
 * Anordnung der Startseite (Migration 147): welche Kachel in welchem der
 * drei Bereiche steht und in welcher Reihenfolge. Eine Kachel, die in
 * keinem Bereich vorkommt, ist ausgeblendet.
 *
 * Das Objekt kennt nur Kachel-Schlüssel und prüft weder, ob es sie gibt,
 * noch ob ein Benutzer sie sehen darf – beides entscheidet
 * App\Application\Dashboard\DashboardTileCatalog (siehe only()).
 */
final class DashboardLayout
{
    /** Breite linke Spalte. */
    public const string ZONE_MAIN = 'main';
    /** Schmale rechte Spalte. */
    public const string ZONE_SIDE = 'side';
    /** Dreispaltige Reihe unter den beiden Spalten. */
    public const string ZONE_BOTTOM = 'bottom';

    /** @var list<string> */
    public const array ZONES = [self::ZONE_MAIN, self::ZONE_SIDE, self::ZONE_BOTTOM];

    /** @param array<string, list<string>> $zones je Bereich die Kachel-Schlüssel in Anzeigereihenfolge */
    private function __construct(private readonly array $zones)
    {
    }

    /**
     * Baut eine Anordnung aus ungeprüften Daten (Formular, Datenbank):
     * unbekannte Bereiche und Nicht-Strings fallen weg, eine mehrfach
     * genannte Kachel bleibt nur an ihrer ersten Stelle stehen.
     *
     * @param array<mixed> $raw
     */
    public static function fromZones(array $raw): self
    {
        $zones = [];
        $seen = [];
        foreach (self::ZONES as $zone) {
            $zones[$zone] = [];
            $keys = $raw[$zone] ?? [];
            if (!is_array($keys)) {
                continue;
            }
            foreach ($keys as $key) {
                if (!is_string($key) || $key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $zones[$zone][] = $key;
            }
        }

        return new self($zones);
    }

    /** NULL (auch bei unlesbarem Inhalt) bedeutet „keine eigene Anordnung gespeichert“. */
    public static function fromJson(?string $json): ?self
    {
        if ($json === null || $json === '') {
            return null;
        }
        $raw = json_decode($json, true);

        return is_array($raw) ? self::fromZones($raw) : null;
    }

    public function toJson(): string
    {
        return (string) json_encode($this->zones, JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, list<string>> */
    public function zones(): array
    {
        return $this->zones;
    }

    /** @return list<string> */
    public function zone(string $zone): array
    {
        return $this->zones[$zone] ?? [];
    }

    /** @return list<string> alle sichtbaren Kacheln, Bereich für Bereich */
    public function keys(): array
    {
        return array_merge(...array_values($this->zones));
    }

    public function zoneOf(string $key): ?string
    {
        foreach ($this->zones as $zone => $keys) {
            if (in_array($key, $keys, true)) {
                return $zone;
            }
        }

        return null;
    }

    public function isEmpty(): bool
    {
        return $this->keys() === [];
    }

    /**
     * Dieselbe Anordnung, beschränkt auf die genannten Kacheln.
     *
     * @param list<string> $allowedKeys
     */
    public function only(array $allowedKeys): self
    {
        $zones = [];
        foreach ($this->zones as $zone => $keys) {
            $zones[$zone] = array_values(array_filter($keys, static fn (string $key) => in_array($key, $allowedKeys, true)));
        }

        return new self($zones);
    }
}
