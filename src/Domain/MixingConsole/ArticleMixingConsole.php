<?php

declare(strict_types=1);

namespace App\Domain\MixingConsole;

/**
 * Mixing console details of an article (migration 154).
 *
 * Analog consoles: input channels with mic preamp, stereo inputs, combo
 * inputs, aux sends (pre, post, switchable), subgroups and mono master.
 *
 * Digital consoles: built-in digital interfaces, DSP channels (mono/stereo
 * with manufacturer designation), local analog inputs/outputs, expansion
 * slots with the cards that fit them and compatible digital stageboxes.
 *
 * Fields of the other type are always 0/empty.
 */
final class ArticleMixingConsole
{
    public const MAX_COUNT = 999;
    public const MAX_INTERFACE_QUANTITY = 99;

    /**
     * @param MixingConsoleInterface[] $interfaces
     * @param MixingConsoleChannel[] $channels
     * @param MixingConsoleExpansionCard[] $cards
     * @param MixingConsoleStagebox[] $stageboxes
     */
    public function __construct(
        public readonly string $articleId,
        public MixingConsoleType $type,
        // Analog
        public int $micInputs = 0,
        public int $stereoInputs = 0,
        public int $comboInputs = 0,
        public int $auxPre = 0,
        public int $auxPost = 0,
        public int $auxSwitchable = 0,
        public int $subgroups = 0,
        public bool $hasMonoMaster = false,
        // Digital
        public int $localInputs = 0,
        public int $localOutputs = 0,
        public int $expansionSlots = 0,
        public ?string $expansionSlotType = null,
        public array $interfaces = [],
        public array $channels = [],
        public array $cards = [],
        public array $stageboxes = [],
    ) {
    }

    public function isDigital(): bool
    {
        return $this->type === MixingConsoleType::Digital;
    }

    public function totalAnalogInputs(): int
    {
        return $this->micInputs + $this->stereoInputs + $this->comboInputs;
    }

    public function totalAux(): int
    {
        return $this->auxPre + $this->auxPost + $this->auxSwitchable;
    }

    /** Analog input channels, e.g. "20 (16 × Mikrofon, 4 × Stereo)". */
    public function analogInputsLabel(): string
    {
        return self::label($this->totalAnalogInputs(), ['Mikrofon' => $this->micInputs, 'Stereo' => $this->stereoInputs, 'Kombi' => $this->comboInputs]);
    }

    /** Aux sends, e.g. "6 (2 × Pre, 2 × Post, 2 × umschaltbar)". */
    public function auxLabel(): string
    {
        return self::label($this->totalAux(), ['Pre' => $this->auxPre, 'Post' => $this->auxPost, 'umschaltbar' => $this->auxSwitchable]);
    }

    /** Number of DSP channels of the given kind (stereo channels count once). */
    public function channelCount(MixingConsoleChannelKind $kind): int
    {
        $count = 0;
        foreach ($this->channels as $channel) {
            if ($channel->kind === $kind) {
                $count += $channel->quantity;
            }
        }

        return $count;
    }

    /**
     * Protocol IDs the console offers, built in or via one of its expansion cards.
     *
     * @return list<string>
     */
    public function protocolIds(): array
    {
        $ids = array_map(static fn (MixingConsoleInterface $i) => $i->protocolId, $this->interfaces);
        foreach ($this->cards as $card) {
            if ($card->protocolId !== null) {
                $ids[] = $card->protocolId;
            }
        }

        return array_values(array_unique($ids));
    }

    /** @return string[] stagebox article IDs with the given compatibility */
    public function stageboxIds(StageboxCompatibility $compatibility): array
    {
        return array_values(array_map(
            static fn (MixingConsoleStagebox $s) => $s->stageboxArticleId,
            array_filter($this->stageboxes, static fn (MixingConsoleStagebox $s) => $s->compatibility === $compatibility),
        ));
    }

    /** Short summary for badges, e.g. "Analog · 24 Kanäle" or "Digital · 48 Eingangskanäle". */
    public function summary(): string
    {
        if (!$this->isDigital()) {
            return $this->type->label() . ' · ' . $this->totalAnalogInputs() . ' Kanäle';
        }
        $inputs = $this->channelCount(MixingConsoleChannelKind::Input);

        return $this->type->label() . ($inputs > 0 ? ' · ' . $inputs . ' Eingangskanäle' : '');
    }

    /** @param array<string, int> $parts */
    private static function label(int $total, array $parts): string
    {
        $used = array_filter($parts, static fn (int $count) => $count > 0);
        if ($used === []) {
            return 'keine';
        }
        $text = implode(', ', array_map(static fn (string $name, int $count) => $count . ' × ' . $name, array_keys($used), $used));

        return count($used) === 1 ? $text : $total . ' (' . $text . ')';
    }
}
