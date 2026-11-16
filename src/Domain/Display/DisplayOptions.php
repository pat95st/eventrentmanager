<?php

declare(strict_types=1);

namespace App\Domain\Display;

/**
 * Layout options of a display device (migration 168), stored as JSON in
 * display_clients.options_json and passed unchanged to the client in the
 * display API (see docs/display-api.md). Only ADD keys, never rename them.
 */
final class DisplayOptions
{
    /** Trades whose notes a minute program item can carry (see RunSheetPayload::notes()). */
    public const NOTE_TRADES = [
        'general' => 'Allgemein',
        'sound' => 'Ton',
        'light' => 'Licht',
        'video' => 'Video',
        'stage' => 'Bühne',
    ];

    public const THEMES = ['dark' => 'Dunkel', 'light' => 'Hell'];

    /**
     * @param list<string> $notes trades whose notes are shown
     */
    public function __construct(
        public array $notes = ['general', 'stage'],
        public bool $showClock = true,
        public bool $showDelay = true,
        public bool $showNext = true,
        public bool $showItemList = false,
        public bool $showDescription = true,
        public bool $showMics = false,
        public bool $allowControl = false,
        public string $theme = 'dark',
        public int $fontScale = 100,
    ) {
        $this->notes = self::sanitizeNotes($notes);
        $this->theme = isset(self::THEMES[$theme]) ? $theme : 'dark';
        $this->fontScale = max(50, min(200, $fontScale));
    }

    /** Sensible defaults for a freshly created device of the given role. */
    public static function defaultsFor(DisplayRole $role): self
    {
        return match ($role) {
            DisplayRole::Stage => new self(),
            DisplayRole::Foh => new self(
                notes: array_keys(self::NOTE_TRADES),
                showItemList: true,
                showMics: true,
                allowControl: true,
            ),
        };
    }

    /** @param array<string, mixed>|null $data */
    public static function fromArray(?array $data, DisplayRole $role = DisplayRole::Stage): self
    {
        $defaults = self::defaultsFor($role);
        if ($data === null) {
            return $defaults;
        }
        $bool = static fn (string $key, bool $default): bool => array_key_exists($key, $data) ? (bool) $data[$key] : $default;

        return new self(
            notes: is_array($data['notes'] ?? null) ? array_values(array_map('strval', $data['notes'])) : $defaults->notes,
            showClock: $bool('showClock', $defaults->showClock),
            showDelay: $bool('showDelay', $defaults->showDelay),
            showNext: $bool('showNext', $defaults->showNext),
            showItemList: $bool('showItemList', $defaults->showItemList),
            showDescription: $bool('showDescription', $defaults->showDescription),
            showMics: $bool('showMics', $defaults->showMics),
            allowControl: $bool('allowControl', $defaults->allowControl),
            theme: is_string($data['theme'] ?? null) ? $data['theme'] : $defaults->theme,
            fontScale: is_numeric($data['fontScale'] ?? null) ? (int) $data['fontScale'] : $defaults->fontScale,
        );
    }

    /** @return array{notes: list<string>, showClock: bool, showDelay: bool, showNext: bool, showItemList: bool, showDescription: bool, showMics: bool, allowControl: bool, theme: string, fontScale: int} */
    public function toArray(): array
    {
        return [
            'notes' => $this->notes,
            'showClock' => $this->showClock,
            'showDelay' => $this->showDelay,
            'showNext' => $this->showNext,
            'showItemList' => $this->showItemList,
            'showDescription' => $this->showDescription,
            'showMics' => $this->showMics,
            'allowControl' => $this->allowControl,
            'theme' => $this->theme,
            'fontScale' => $this->fontScale,
        ];
    }

    public function showsNotes(string $trade): bool
    {
        return in_array($trade, $this->notes, true);
    }

    /**
     * @param array<mixed> $notes
     *
     * @return list<string>
     */
    private static function sanitizeNotes(array $notes): array
    {
        // Keep the canonical order of NOTE_TRADES, drop unknown values.
        return array_values(array_filter(array_keys(self::NOTE_TRADES), static fn (string $t): bool => in_array($t, $notes, true)));
    }
}
