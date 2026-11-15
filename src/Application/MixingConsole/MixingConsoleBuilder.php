<?php

declare(strict_types=1);

namespace App\Application\MixingConsole;

use App\Domain\DigitalStagebox\DigitalStageboxRepositoryInterface;
use App\Domain\MixingConsole\ArticleMixingConsole;
use App\Domain\MixingConsole\MixingConsoleChannel;
use App\Domain\MixingConsole\MixingConsoleChannelKind;
use App\Domain\MixingConsole\MixingConsoleExpansionCard;
use App\Domain\MixingConsole\MixingConsoleInterface;
use App\Domain\MixingConsole\MixingConsoleRepositoryInterface;
use App\Domain\MixingConsole\MixingConsoleStagebox;
use App\Domain\MixingConsole\MixingConsoleType;
use App\Domain\MixingConsole\StageboxCompatibility;

/**
 * Validates the mixing console fields of the article form (migration 154)
 * and builds an ArticleMixingConsole. Empty count fields count as 0, rows
 * without any value are ignored. Only the fields of the selected type are
 * kept; those of the other type are reset.
 *
 * Stagebox rules: a stagebox must use a protocol the console offers (built
 * in or via an expansion card). "Native" is only allowed for stageboxes of
 * the console's manufacturer; others with the same protocol can only be
 * marked as "compatible with limitations".
 */
final class MixingConsoleBuilder
{
    public function __construct(
        private readonly MixingConsoleRepositoryInterface $consoles,
        private readonly DigitalStageboxRepositoryInterface $protocols,
    ) {
    }

    /** @return array{0: array<string, string>, 1: ?ArticleMixingConsole} */
    public function build(string $articleId, MixingConsoleInput $input, ?string $consoleManufacturerId = null): array
    {
        $type = MixingConsoleType::tryFrom((string) $input->type);
        if ($type === null) {
            return [['mc_type' => 'Bitte wählen, ob es ein analoges oder digitales Tonmischpult ist.'], null];
        }

        return $type === MixingConsoleType::Analog
            ? $this->buildAnalog($articleId, $input)
            : $this->buildDigital($articleId, $input, $consoleManufacturerId);
    }

    /** @return array{0: array<string, string>, 1: ?ArticleMixingConsole} */
    private function buildAnalog(string $articleId, MixingConsoleInput $input): array
    {
        $errors = [];
        $counts = $this->counts([
            'mc_mic_inputs' => [$input->micInputs, 'Eingangskanäle mit Mikrofonvorverstärker'],
            'mc_stereo_inputs' => [$input->stereoInputs, 'Stereo-Eingänge'],
            'mc_combo_inputs' => [$input->comboInputs, 'Kombi-Eingänge'],
            'mc_aux_pre' => [$input->auxPre, 'Pre-AUX-Wege'],
            'mc_aux_post' => [$input->auxPost, 'Post-AUX-Wege'],
            'mc_aux_switchable' => [$input->auxSwitchable, 'umschaltbaren AUX-Wege'],
            'mc_subgroups' => [$input->subgroups, 'Subgruppen'],
        ], $errors);

        $inputFields = ['mc_mic_inputs' => true, 'mc_stereo_inputs' => true, 'mc_combo_inputs' => true];
        if (
            array_intersect_key($errors, $inputFields) === []
            && $counts['mc_mic_inputs'] + $counts['mc_stereo_inputs'] + $counts['mc_combo_inputs'] === 0
        ) {
            $errors['mc_mic_inputs'] = 'Ein analoges Tonmischpult braucht mindestens einen Eingangskanal.';
        }
        if ($errors !== []) {
            return [$errors, null];
        }

        return [[], new ArticleMixingConsole(
            $articleId,
            MixingConsoleType::Analog,
            micInputs: $counts['mc_mic_inputs'],
            stereoInputs: $counts['mc_stereo_inputs'],
            comboInputs: $counts['mc_combo_inputs'],
            auxPre: $counts['mc_aux_pre'],
            auxPost: $counts['mc_aux_post'],
            auxSwitchable: $counts['mc_aux_switchable'],
            subgroups: $counts['mc_subgroups'],
            hasMonoMaster: $input->hasMonoMaster,
        )];
    }

    /** @return array{0: array<string, string>, 1: ?ArticleMixingConsole} */
    private function buildDigital(string $articleId, MixingConsoleInput $input, ?string $consoleManufacturerId): array
    {
        $errors = [];
        $counts = $this->counts([
            'mc_local_inputs' => [$input->localInputs, 'lokalen analogen Eingänge'],
            'mc_local_outputs' => [$input->localOutputs, 'lokalen analogen Ausgänge'],
            'mc_expansion_slots' => [$input->expansionSlots, 'Erweiterungsslots'],
        ], $errors);
        $slotType = trim((string) $input->expansionSlotType);
        if (mb_strlen($slotType) > 100) {
            $errors['mc_expansion_slot_type'] = 'Die Slot-Bezeichnung darf höchstens 100 Zeichen lang sein.';
        }

        $interfaces = $this->interfaces($input->interfaces, $errors);
        $channels = $this->channels($input->channels, $errors);
        $cards = $this->cards($input->cards, $errors);
        if ($cards !== [] && !isset($errors['mc_expansion_slots']) && $counts['mc_expansion_slots'] === 0) {
            $errors['mc_expansion_slots'] = 'Für Erweiterungskarten bitte die Anzahl der Erweiterungsslots angeben.';
        }

        $console = new ArticleMixingConsole(
            $articleId,
            MixingConsoleType::Digital,
            localInputs: $counts['mc_local_inputs'],
            localOutputs: $counts['mc_local_outputs'],
            expansionSlots: $counts['mc_expansion_slots'],
            expansionSlotType: $slotType !== '' ? $slotType : null,
            interfaces: $interfaces,
            channels: $channels,
            cards: $cards,
        );
        $console->stageboxes = $this->stageboxes($input->stageboxes, $console->protocolIds(), $consoleManufacturerId, $errors);

        return $errors !== [] ? [$errors, null] : [[], $console];
    }

    /**
     * @param list<array<string, string>> $rows
     * @param array<string, string> $errors
     * @return MixingConsoleInterface[]
     */
    private function interfaces(array $rows, array &$errors): array
    {
        $result = [];
        foreach ($rows as $row) {
            $protocolId = trim($row['protocol_id'] ?? '');
            $rawQuantity = trim($row['quantity'] ?? '');
            if ($protocolId === '' && $rawQuantity === '') {
                continue;
            }
            $protocol = $protocolId !== '' ? $this->protocols->findProtocolById($protocolId) : null;
            if ($protocol === null) {
                $errors['mc_interfaces'] = 'Bitte für jede digitale Schnittstelle ein Protokoll wählen (siehe Einstellungen › Digitale Protokolle).';
                continue;
            }
            $quantity = $rawQuantity === '' ? 1 : self::number($rawQuantity, ArticleMixingConsole::MAX_INTERFACE_QUANTITY);
            if ($quantity === null || $quantity === 0) {
                $errors['mc_interfaces'] = 'Die Anzahl je Schnittstelle muss eine ganze Zahl von 1 bis ' . ArticleMixingConsole::MAX_INTERFACE_QUANTITY . ' sein.';
                continue;
            }
            if (isset($result[$protocol->id])) {
                $errors['mc_interfaces'] = 'Die Schnittstelle „' . $protocol->name . '“ ist doppelt angegeben – bitte die Anzahl in einer Zeile zusammenfassen.';
                continue;
            }
            $result[$protocol->id] = new MixingConsoleInterface($protocol->id, $quantity);
        }

        return array_values($result);
    }

    /**
     * @param list<array<string, string>> $rows
     * @param array<string, string> $errors
     * @return MixingConsoleChannel[]
     */
    private function channels(array $rows, array &$errors): array
    {
        $result = [];
        foreach ($rows as $row) {
            $rawQuantity = trim($row['quantity'] ?? '');
            $label = trim($row['label'] ?? '');
            if ($rawQuantity === '' && $label === '') {
                continue;
            }
            $kind = MixingConsoleChannelKind::tryFrom($row['kind'] ?? '');
            if ($kind === null) {
                $errors['mc_channels'] = 'Bitte für jeden DSP-Kanal eine Kanalart wählen.';
                continue;
            }
            $quantity = self::number($rawQuantity, ArticleMixingConsole::MAX_COUNT);
            if ($quantity === null || $quantity === 0) {
                $errors['mc_channels'] = 'Die Anzahl je DSP-Kanal muss eine ganze Zahl von 1 bis ' . ArticleMixingConsole::MAX_COUNT . ' sein.';
                continue;
            }
            if (mb_strlen($label) > 100) {
                $errors['mc_channels'] = 'Die Herstellerbezeichnung eines DSP-Kanals darf höchstens 100 Zeichen lang sein.';
                continue;
            }
            $result[] = new MixingConsoleChannel($kind, $quantity, ($row['stereo'] ?? '') === '1', $label !== '' ? $label : null);
        }

        return $result;
    }

    /**
     * @param list<array<string, string>> $rows
     * @param array<string, string> $errors
     * @return MixingConsoleExpansionCard[]
     */
    private function cards(array $rows, array &$errors): array
    {
        $result = [];
        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            $protocolId = trim($row['protocol_id'] ?? '');
            $rawInputs = trim($row['inputs'] ?? '');
            $rawOutputs = trim($row['outputs'] ?? '');
            $notes = trim($row['notes'] ?? '');
            if ($name === '' && $protocolId === '' && $rawInputs === '' && $rawOutputs === '' && $notes === '') {
                continue;
            }
            if ($name === '' || mb_strlen($name) > 150) {
                $errors['mc_cards'] = 'Bitte für jede Erweiterungskarte eine Bezeichnung angeben (max. 150 Zeichen).';
                continue;
            }
            $protocol = $protocolId !== '' ? $this->protocols->findProtocolById($protocolId) : null;
            if ($protocolId !== '' && $protocol === null) {
                $errors['mc_cards'] = 'Das Protokoll der Erweiterungskarte „' . $name . '“ wurde nicht gefunden.';
                continue;
            }
            $inputs = self::number($rawInputs, ArticleMixingConsole::MAX_COUNT);
            $outputs = self::number($rawOutputs, ArticleMixingConsole::MAX_COUNT);
            if ($inputs === null || $outputs === null) {
                $errors['mc_cards'] = 'Ein- und Ausgänge der Erweiterungskarte „' . $name . '“ bitte als ganze Zahl von 0 bis ' . ArticleMixingConsole::MAX_COUNT . ' angeben.';
                continue;
            }
            if (mb_strlen($notes) > 255) {
                $errors['mc_cards'] = 'Die Bemerkung zur Erweiterungskarte „' . $name . '“ darf höchstens 255 Zeichen lang sein.';
                continue;
            }
            $result[] = new MixingConsoleExpansionCard($name, $protocol?->id, $inputs, $outputs, $notes !== '' ? $notes : null);
        }

        return $result;
    }

    /**
     * @param array<string, string> $selection stagebox article ID => compatibility
     * @param list<string> $consoleProtocolIds
     * @param array<string, string> $errors
     * @return MixingConsoleStagebox[]
     */
    private function stageboxes(array $selection, array $consoleProtocolIds, ?string $consoleManufacturerId, array &$errors): array
    {
        $selection = array_filter($selection, static fn (string $value) => $value !== '');
        if ($selection === []) {
            return [];
        }
        $options = [];
        foreach ($this->consoles->findStageboxOptions() as $option) {
            $options[$option->articleId] = $option;
        }

        $result = [];
        foreach ($selection as $stageboxId => $rawCompatibility) {
            $stageboxId = (string) $stageboxId;
            $compatibility = StageboxCompatibility::tryFrom($rawCompatibility);
            $option = $options[$stageboxId] ?? null;
            if ($compatibility === null || $option === null) {
                $errors['mc_stageboxes'] = 'Eine ausgewählte Stagebox wurde nicht gefunden.';
                continue;
            }
            if (!in_array($option->protocolId, $consoleProtocolIds, true)) {
                $protocol = $this->protocols->findProtocolById($option->protocolId);
                $errors['mc_stageboxes'] = 'Die Stagebox „' . $option->displayName() . '“ nutzt ' . ($protocol !== null ? '„' . $protocol->name . '“' : 'ein Protokoll')
                    . ', das das Pult weder als Schnittstelle noch über eine Erweiterungskarte bietet.';
                continue;
            }
            if (
                $compatibility === StageboxCompatibility::Native
                && $consoleManufacturerId !== null && $consoleManufacturerId !== ''
                && $option->manufacturerId !== null && $option->manufacturerId !== $consoleManufacturerId
            ) {
                $errors['mc_stageboxes'] = 'Die Stagebox „' . $option->displayName() . '“ ist von einem anderen Hersteller und kann nur als „kompatibel mit Einschränkungen“ angegeben werden.';
                continue;
            }
            $result[] = new MixingConsoleStagebox($stageboxId, $compatibility);
        }

        return $result;
    }

    /**
     * @param array<string, array{0: ?string, 1: string}> $fields
     * @param array<string, string> $errors
     * @return array<string, int>
     */
    private function counts(array $fields, array &$errors): array
    {
        $counts = [];
        foreach ($fields as $field => [$raw, $label]) {
            $count = self::number(trim((string) $raw), ArticleMixingConsole::MAX_COUNT);
            if ($count === null) {
                $errors[$field] = 'Bitte die Anzahl der ' . $label . ' als ganze Zahl von 0 bis ' . ArticleMixingConsole::MAX_COUNT . ' angeben.';
            }
            $counts[$field] = $count ?? 0;
        }

        return $counts;
    }

    /** Empty = 0; otherwise an integer from 0 to $max, invalid = null. */
    private static function number(string $raw, int $max): ?int
    {
        if ($raw === '') {
            return 0;
        }
        if (preg_match('/^\d{1,4}$/', $raw) !== 1) {
            return null;
        }
        $value = (int) $raw;

        return $value <= $max ? $value : null;
    }
}
