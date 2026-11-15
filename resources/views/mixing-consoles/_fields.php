<?php
/**
 * Felder eines Tonmischpults (Migration 153) im Artikelformular. Analog:
 * Eingänge, AUX-Wege, Subgruppen, Mono-Master. Digital: Schnittstellen,
 * DSP-Kanäle, lokale Ein-/Ausgänge, Erweiterungsslots/-karten und
 * kompatible Stageboxen. Zeilen werden per /assets/js/mixing-console-fields.js
 * ergänzt; Feldnamen mc_*, mc_interfaces[i][…], mc_channels[i][…],
 * mc_cards[i][…], mc_stageboxes[<Artikel-ID>].
 *
 * @var \App\Domain\MixingConsole\ArticleMixingConsole|null $console gespeicherte Angaben
 * @var \App\Application\MixingConsole\MixingConsoleInput|null $old nach Validierungsfehler
 * @var \App\Application\MixingConsole\Query\MixingConsoleFormData $formData
 * @var string|null $manufacturerId aktuell gewählter Hersteller des Pults
 * @var array<string, string> $errors
 */

use App\Domain\MixingConsole\ArticleMixingConsole;
use App\Domain\MixingConsole\MixingConsoleChannelKind;
use App\Domain\MixingConsole\MixingConsoleType;
use App\Domain\MixingConsole\StageboxCompatibility;
use function App\Infrastructure\Http\e;

$input = 'w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900';
$label = 'block text-[11px] text-slate-500 dark:text-slate-400';
$countInput = static fn (string $key) => 'mt-1 w-full rounded-md border ' . (isset($errors[$key]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700') . ' px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500';
$err = static fn (string $key) => isset($errors[$key]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$key]) . '</p>' : '';
$count = static fn (int $value) => $value > 0 ? (string) $value : '';

if ($old !== null) {
    $type = (string) $old->type;
    $values = [
        'mc_mic_inputs' => (string) $old->micInputs, 'mc_stereo_inputs' => (string) $old->stereoInputs, 'mc_combo_inputs' => (string) $old->comboInputs,
        'mc_aux_pre' => (string) $old->auxPre, 'mc_aux_post' => (string) $old->auxPost, 'mc_aux_switchable' => (string) $old->auxSwitchable,
        'mc_subgroups' => (string) $old->subgroups, 'mc_local_inputs' => (string) $old->localInputs, 'mc_local_outputs' => (string) $old->localOutputs,
        'mc_expansion_slots' => (string) $old->expansionSlots, 'mc_expansion_slot_type' => (string) $old->expansionSlotType,
    ];
    $monoMaster = $old->hasMonoMaster;
    $interfaceRows = $old->interfaces;
    $channelRows = $old->channels;
    $cardRows = $old->cards;
    $stageboxSelection = $old->stageboxes;
} else {
    $type = $console?->type->value ?? '';
    $values = [
        'mc_mic_inputs' => $count($console?->micInputs ?? 0), 'mc_stereo_inputs' => $count($console?->stereoInputs ?? 0),
        'mc_combo_inputs' => $count($console?->comboInputs ?? 0), 'mc_aux_pre' => $count($console?->auxPre ?? 0),
        'mc_aux_post' => $count($console?->auxPost ?? 0), 'mc_aux_switchable' => $count($console?->auxSwitchable ?? 0),
        'mc_subgroups' => $count($console?->subgroups ?? 0), 'mc_local_inputs' => $count($console?->localInputs ?? 0),
        'mc_local_outputs' => $count($console?->localOutputs ?? 0), 'mc_expansion_slots' => $count($console?->expansionSlots ?? 0),
        'mc_expansion_slot_type' => (string) ($console?->expansionSlotType ?? ''),
    ];
    $monoMaster = $console?->hasMonoMaster ?? false;
    $interfaceRows = array_map(static fn ($i) => ['protocol_id' => $i->protocolId, 'quantity' => (string) $i->quantity], $console?->interfaces ?? []);
    $channelRows = array_map(static fn ($c) => [
        'kind' => $c->kind->value, 'quantity' => (string) $c->quantity, 'stereo' => $c->isStereo ? '1' : '', 'label' => (string) $c->manufacturerLabel,
    ], $console?->channels ?? []);
    $cardRows = array_map(static fn ($c) => [
        'name' => $c->name, 'protocol_id' => (string) $c->protocolId, 'inputs' => (string) $c->inputs, 'outputs' => (string) $c->outputs, 'notes' => (string) $c->notes,
    ], $console?->cards ?? []);
    $stageboxSelection = [];
    foreach ($console?->stageboxes ?? [] as $stagebox) {
        $stageboxSelection[$stagebox->stageboxArticleId] = $stagebox->compatibility->value;
    }
}

$protocolOptions = static function (string $selected, string $empty) use ($formData): string {
    $html = '<option value="">' . e($empty) . '</option>';
    foreach ($formData->protocols as $protocol) {
        $html .= '<option value="' . e($protocol->id) . '"' . ($protocol->id === $selected ? ' selected' : '') . '>' . e($protocol->name) . '</option>';
    }

    return $html;
};
$removeButton = '<button type="button" data-remove-row class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>';

$renderInterfaceRow = static function (string $index, array $row) use ($input, $label, $protocolOptions, $removeButton): void {
    $name = static fn (string $field) => 'mc_interfaces[' . $index . '][' . $field . ']';
    ?>
    <div data-row class="flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <div style="flex:3 1 12rem">
            <label class="<?= $label ?>">Protokoll</label>
            <select name="<?= $name('protocol_id') ?>" class="<?= $input ?>" data-protocol-select><?= $protocolOptions($row['protocol_id'] ?? '', 'Bitte wählen…') ?></select>
        </div>
        <div style="flex:0 0 6rem">
            <label class="<?= $label ?>">Anzahl</label>
            <input type="number" min="1" max="<?= ArticleMixingConsole::MAX_INTERFACE_QUANTITY ?>" name="<?= $name('quantity') ?>" value="<?= e($row['quantity'] ?? '1') ?>" class="<?= $input ?>">
        </div>
        <div class="flex justify-end" style="flex:0 0 auto"><?= $removeButton ?></div>
    </div>
    <?php
};

$renderChannelRow = static function (string $index, array $row) use ($input, $label, $removeButton): void {
    $name = static fn (string $field) => 'mc_channels[' . $index . '][' . $field . ']';
    ?>
    <div data-row class="flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <div style="flex:2 1 10rem">
            <label class="<?= $label ?>">Kanalart</label>
            <select name="<?= $name('kind') ?>" class="<?= $input ?>">
                <?php foreach (MixingConsoleChannelKind::cases() as $kind): ?>
                    <option value="<?= e($kind->value) ?>" <?= ($row['kind'] ?? 'input') === $kind->value ? 'selected' : '' ?>><?= e($kind->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex:3 1 12rem">
            <label class="<?= $label ?>">Herstellerbezeichnung</label>
            <input name="<?= $name('label') ?>" value="<?= e($row['label'] ?? '') ?>" maxlength="100" placeholder="z. B. Mix Bus, Input Channel" class="<?= $input ?>">
        </div>
        <div style="flex:0 0 6rem">
            <label class="<?= $label ?>">Anzahl</label>
            <input type="number" min="1" max="<?= ArticleMixingConsole::MAX_COUNT ?>" name="<?= $name('quantity') ?>" value="<?= e($row['quantity'] ?? '') ?>" class="<?= $input ?>">
        </div>
        <div style="flex:0 0 auto">
            <label class="<?= $label ?>">Ausführung</label>
            <div class="flex gap-3 py-1.5 text-sm text-slate-700 dark:text-slate-300">
                <label class="flex items-center gap-1"><input type="radio" name="<?= $name('stereo') ?>" value="" <?= ($row['stereo'] ?? '') !== '1' ? 'checked' : '' ?>> Mono</label>
                <label class="flex items-center gap-1"><input type="radio" name="<?= $name('stereo') ?>" value="1" <?= ($row['stereo'] ?? '') === '1' ? 'checked' : '' ?>> Stereo</label>
            </div>
        </div>
        <div class="flex justify-end" style="flex:0 0 auto"><?= $removeButton ?></div>
    </div>
    <?php
};

$renderCardRow = static function (string $index, array $row) use ($input, $label, $protocolOptions, $removeButton): void {
    $name = static fn (string $field) => 'mc_cards[' . $index . '][' . $field . ']';
    ?>
    <div data-row class="flex flex-wrap items-end gap-2 rounded-md border border-slate-200 p-3 dark:border-slate-700">
        <div style="flex:3 1 12rem">
            <label class="<?= $label ?>">Bezeichnung</label>
            <input name="<?= $name('name') ?>" value="<?= e($row['name'] ?? '') ?>" maxlength="150" placeholder="z. B. Dante-Karte 64×64" class="<?= $input ?>">
        </div>
        <div style="flex:2 1 9rem">
            <label class="<?= $label ?>">Protokoll</label>
            <select name="<?= $name('protocol_id') ?>" class="<?= $input ?>" data-protocol-select><?= $protocolOptions($row['protocol_id'] ?? '', 'analog / kein Protokoll') ?></select>
        </div>
        <div style="flex:0 0 5.5rem">
            <label class="<?= $label ?>">Eingänge</label>
            <input type="number" min="0" max="<?= ArticleMixingConsole::MAX_COUNT ?>" name="<?= $name('inputs') ?>" value="<?= e($row['inputs'] ?? '') ?>" placeholder="0" class="<?= $input ?>">
        </div>
        <div style="flex:0 0 5.5rem">
            <label class="<?= $label ?>">Ausgänge</label>
            <input type="number" min="0" max="<?= ArticleMixingConsole::MAX_COUNT ?>" name="<?= $name('outputs') ?>" value="<?= e($row['outputs'] ?? '') ?>" placeholder="0" class="<?= $input ?>">
        </div>
        <div style="flex:2 1 9rem">
            <label class="<?= $label ?>">Bemerkung</label>
            <input name="<?= $name('notes') ?>" value="<?= e($row['notes'] ?? '') ?>" maxlength="255" placeholder="optional" class="<?= $input ?>">
        </div>
        <div class="flex justify-end" style="flex:0 0 auto"><?= $removeButton ?></div>
    </div>
    <?php
};

$addButton = static fn (string $kind, string $caption) => '<button type="button" data-add-row="' . $kind . '" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">' . e($caption) . '</button>';
$rowErrors = static fn (string $key) => isset($errors[$key]) ? '<p class="mb-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-800 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800">' . e($errors[$key]) . '</p>' : '';

$countField = static function (string $field, string $caption, array $values, ?string $hint = null) use ($countInput): void {
    ?>
    <div>
        <label for="<?= $field ?>" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e($caption) ?></label>
        <input type="number" min="0" max="<?= ArticleMixingConsole::MAX_COUNT ?>" step="1" inputmode="numeric" placeholder="0"
               id="<?= $field ?>" name="<?= $field ?>" value="<?= e($values[$field] ?? '') ?>" data-mc-count class="<?= $countInput($field) ?>">
        <?php if ($hint !== null): ?><p class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500"><?= e($hint) ?></p><?php endif; ?>
    </div>
    <?php
};
?>
<div data-mixing-console-fields class="space-y-5" data-manufacturer-id="<?= e((string) $manufacturerId) ?>">
    <fieldset>
        <legend class="block text-xs font-medium text-slate-600 dark:text-slate-400">Bauart *</legend>
        <div class="mt-2 flex flex-wrap gap-4">
            <?php foreach (MixingConsoleType::cases() as $typeOption): ?>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="radio" name="mc_type" value="<?= e($typeOption->value) ?>" data-mc-type <?= $type === $typeOption->value ? 'checked' : '' ?>>
                    <?= e($typeOption->label()) ?>es Pult
                </label>
            <?php endforeach; ?>
        </div>
        <?= $err('mc_type') ?>
    </fieldset>

    <!-- Analog -->
    <div data-mc-pane="analog" class="space-y-4 <?= $type === 'analog' ? '' : 'hidden' ?>">
        <fieldset data-mc-sum>
            <legend class="text-xs font-semibold text-slate-700 dark:text-slate-300">Eingänge <span class="font-normal text-slate-500 dark:text-slate-400">· gesamt <span data-mc-total>0</span></span></legend>
            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <?php $countField('mc_mic_inputs', 'Eingangskanäle mit Mikrofonvorverstärker', $values); ?>
                <?php $countField('mc_stereo_inputs', 'Stereo-Eingänge', $values); ?>
                <?php $countField('mc_combo_inputs', 'Kombi-Eingänge', $values); ?>
            </div>
            <?= $err('mc_mic_inputs') ?><?= $err('mc_stereo_inputs') ?><?= $err('mc_combo_inputs') ?>
        </fieldset>
        <fieldset data-mc-sum>
            <legend class="text-xs font-semibold text-slate-700 dark:text-slate-300">AUX-Wege <span class="font-normal text-slate-500 dark:text-slate-400">· gesamt <span data-mc-total>0</span></span></legend>
            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <?php $countField('mc_aux_pre', 'davon Pre-Fader', $values); ?>
                <?php $countField('mc_aux_post', 'davon Post-Fader', $values); ?>
                <?php $countField('mc_aux_switchable', 'davon umschaltbar (Pre/Post)', $values); ?>
            </div>
            <?= $err('mc_aux_pre') ?><?= $err('mc_aux_post') ?><?= $err('mc_aux_switchable') ?>
        </fieldset>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <?php $countField('mc_subgroups', 'Subgruppen', $values); ?>
            <div class="flex items-end">
                <label class="flex items-center gap-2 pb-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="mc_mono_master" value="1" <?= $monoMaster ? 'checked' : '' ?>>
                    MONO-Master vorhanden
                </label>
            </div>
        </div>
        <?= $err('mc_subgroups') ?>
    </div>

    <!-- Digital -->
    <div data-mc-pane="digital" class="space-y-5 <?= $type === 'digital' ? '' : 'hidden' ?>">
        <?php if ($formData->protocols === []): ?>
            <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">Es sind noch keine digitalen Protokolle angelegt (<a href="/einstellungen/digitale-protokolle" class="underline">Einstellungen › Digitale Protokolle</a>).</p>
        <?php endif; ?>

        <div>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Digitale Schnittstellen</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Fest eingebaute Schnittstellen und wie oft sie vorhanden sind, z. B. 2 × AES50. Protokolle pflegst du unter <a href="/einstellungen/digitale-protokolle" class="underline">Einstellungen › Digitale Protokolle</a>.</p>
                </div>
                <?= $addButton('interface', '+ Schnittstelle') ?>
            </div>
            <?= $rowErrors('mc_interfaces') ?>
            <div data-rows="interface" class="space-y-2">
                <?php foreach (array_values($interfaceRows) as $i => $row): $renderInterfaceRow((string) $i, $row); endforeach; ?>
            </div>
            <template data-row-template="interface"><?php $renderInterfaceRow('__INDEX__', []); ?></template>
        </div>

        <div>
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-300">DSP-Kanäle <span class="font-normal text-slate-500 dark:text-slate-400">· <span data-mc-channel-total>0</span> Kanäle</span></p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Je Kanalart mit Anzahl, Mono/Stereo und der Bezeichnung des Herstellers (z. B. „Mix Bus“ statt Aux).</p>
                </div>
                <?= $addButton('channel', '+ DSP-Kanal') ?>
            </div>
            <?= $rowErrors('mc_channels') ?>
            <div data-rows="channel" class="space-y-2">
                <?php foreach (array_values($channelRows) as $i => $row): $renderChannelRow((string) $i, $row); endforeach; ?>
            </div>
            <template data-row-template="channel"><?php $renderChannelRow('__INDEX__', []); ?></template>
        </div>

        <fieldset>
            <legend class="text-sm font-medium text-slate-700 dark:text-slate-300">Lokale analoge Ein- und Ausgänge</legend>
            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <?php $countField('mc_local_inputs', 'Analoge Eingänge', $values); ?>
                <?php $countField('mc_local_outputs', 'Analoge Ausgänge', $values); ?>
            </div>
            <?= $err('mc_local_inputs') ?><?= $err('mc_local_outputs') ?>
        </fieldset>

        <div>
            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Erweiterungskarten</p>
            <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <?php $countField('mc_expansion_slots', 'Anzahl Erweiterungsslots', $values); ?>
                <div>
                    <label for="mc_expansion_slot_type" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Slot-Bezeichnung</label>
                    <input id="mc_expansion_slot_type" name="mc_expansion_slot_type" maxlength="100" value="<?= e($values['mc_expansion_slot_type']) ?>" placeholder="z. B. MY-Slot" class="<?= $countInput('mc_expansion_slot_type') ?>">
                </div>
            </div>
            <?= $err('mc_expansion_slots') ?><?= $err('mc_expansion_slot_type') ?>
            <div class="mb-2 mt-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs text-slate-500 dark:text-slate-400">Mögliche Erweiterungskarten für die Slots mit Protokoll und Anzahl der Kanäle.</p>
                <?= $addButton('card', '+ Erweiterungskarte') ?>
            </div>
            <?= $rowErrors('mc_cards') ?>
            <div data-rows="card" class="space-y-2">
                <?php foreach (array_values($cardRows) as $i => $row): $renderCardRow((string) $i, $row); endforeach; ?>
            </div>
            <template data-row-template="card"><?php $renderCardRow('__INDEX__', []); ?></template>
        </div>

        <div>
            <p class="text-sm font-medium text-slate-700 dark:text-slate-300">Kompatible Stageboxen</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Nativ kompatibel sind Stageboxen, die das Pult direkt unterstützt. Stageboxen, die zwar über das gleiche Protokoll laufen,
                aber z. B. von einem anderen Hersteller sind, können nur als „kompatibel mit Einschränkungen“ angegeben werden.
                Angeboten werden Artikel der Kategorie „Digitale Stagebox“ mit einem Protokoll, das das Pult als Schnittstelle oder per Erweiterungskarte bietet.
            </p>
            <?= $rowErrors('mc_stageboxes') ?>
            <?php if ($formData->stageboxes === []): ?>
                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Noch keine digitalen Stageboxen angelegt.</p>
            <?php else: ?>
                <p data-mc-no-stagebox class="mt-2 hidden text-sm text-slate-500 dark:text-slate-400">Keine Stagebox mit einem Protokoll dieses Pults – zuerst Schnittstellen oder Erweiterungskarten angeben.</p>
                <div class="mt-2 divide-y divide-slate-100 rounded-md border border-slate-200 dark:divide-slate-800 dark:border-slate-700">
                    <?php foreach ($formData->stageboxes as $option): ?>
                        <?php $selected = $stageboxSelection[$option->articleId] ?? ''; ?>
                        <div data-stagebox-row data-protocol="<?= e($option->protocolId) ?>" data-manufacturer="<?= e((string) $option->manufacturerId) ?>" class="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                            <span class="text-sm text-slate-800 dark:text-slate-200">
                                <?= e($option->displayName()) ?>
                                <span class="ml-1 text-xs text-slate-500 dark:text-slate-400"><?= e($formData->protocolName($option->protocolId)) ?></span>
                                <span data-other-manufacturer class="ml-1 hidden text-xs text-amber-700 dark:text-amber-400">anderer Hersteller</span>
                            </span>
                            <select name="mc_stageboxes[<?= e($option->articleId) ?>]" class="rounded-md border border-slate-300 px-2 py-1 text-sm dark:border-slate-700 dark:bg-slate-900" data-stagebox-select>
                                <option value="">nicht kompatibel</option>
                                <?php foreach (StageboxCompatibility::cases() as $compatibility): ?>
                                    <option value="<?= e($compatibility->value) ?>" <?= $selected === $compatibility->value ? 'selected' : '' ?>><?= e($compatibility->label()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
