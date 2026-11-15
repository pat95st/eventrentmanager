<?php

declare(strict_types=1);

namespace App\Application\MixingConsole;

/** Mixing console fields from the article form (migration 153), raw string values. */
final class MixingConsoleInput
{
    /**
     * @param list<array<string, string>> $interfaces rows: protocol_id, quantity
     * @param list<array<string, string>> $channels rows: kind, quantity, stereo ('1'), label
     * @param list<array<string, string>> $cards rows: name, protocol_id, inputs, outputs, notes
     * @param array<string, string> $stageboxes stagebox article ID => '' | 'native' | 'limited'
     */
    public function __construct(
        public readonly ?string $type,
        public readonly ?string $micInputs = null,
        public readonly ?string $stereoInputs = null,
        public readonly ?string $comboInputs = null,
        public readonly ?string $auxPre = null,
        public readonly ?string $auxPost = null,
        public readonly ?string $auxSwitchable = null,
        public readonly ?string $subgroups = null,
        public readonly bool $hasMonoMaster = false,
        public readonly ?string $localInputs = null,
        public readonly ?string $localOutputs = null,
        public readonly ?string $expansionSlots = null,
        public readonly ?string $expansionSlotType = null,
        public readonly array $interfaces = [],
        public readonly array $channels = [],
        public readonly array $cards = [],
        public readonly array $stageboxes = [],
    ) {
    }
}
