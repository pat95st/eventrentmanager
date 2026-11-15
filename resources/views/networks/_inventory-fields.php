<?php
/**
 * IP and MAC addresses in the inventory number form (migration 155).
 * Suggestions per network come from data-network-candidates; the logic is in
 * /assets/js/network-fields.js (switching networks, IPv4/IPv6 fields).
 *
 * @var \App\Application\Network\NetworkInterfaceForm $form
 * @var string $formId unique prefix for element IDs
 */

use App\Application\Network\NetworkInterfaceSlot;

use function App\Infrastructure\Http\e;

$input = 'mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 font-mono text-sm dark:border-slate-700 dark:bg-slate-900';
$networksJson = [];
foreach ($form->networks as $network) {
    $networksJson[$network->id] = [
        'v4' => $network->supports(4),
        'v6' => $network->supports(6),
        'cidr4' => $network->cidr(4),
        'cidr6' => $network->cidr(6),
        'candidates4' => $form->candidates[$network->id][4] ?? [],
        'candidates6' => $form->candidates[$network->id][6] ?? [],
    ];
}
$range = $form->categoryRange;
$rangeNetwork = $range !== null ? $form->network($range->networkId) : null;
?>
<div class="js-network-fields min-w-0 basis-full rounded-md border border-sky-200 bg-sky-50/40 p-3 dark:border-sky-800 dark:bg-sky-950/20"
     data-networks="<?= e((string) json_encode($networksJson, JSON_UNESCAPED_SLASHES)) ?>">
    <input type="hidden" name="network_section" value="1">
    <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
        <h3 class="text-xs font-semibold text-slate-700 dark:text-slate-300">Netzwerk</h3>
        <?php if ($range !== null && $rangeNetwork !== null): ?>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
                Bereich der Kategorie: <?= e($rangeNetwork->name) ?>
                <?php foreach ([4, 6] as $version): ?>
                    <?php if (($label = $range->label($version)) !== null): ?> · <span class="font-mono"><?= e($label) ?></span><?php endif; ?>
                <?php endforeach; ?>
            </p>
        <?php endif; ?>
    </div>
    <?php if ($form->requiresDhcp): ?>
        <p class="mb-2 rounded bg-amber-50 px-2 py-1 text-[11px] text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">
            Dieses Gerät benötigt zwingend einen DHCP-Server – die IP-Adresse wird als DHCP-Reservierung dokumentiert (dafür die MAC-Adresse angeben).
        </p>
    <?php endif; ?>
    <?php if ($form->networks === []): ?>
        <p class="mb-2 text-[11px] text-slate-500 dark:text-slate-400">
            Noch keine Netzwerke angelegt – IP-Adressen lassen sich erst nach dem Anlegen unter
            <a href="/einstellungen/netzwerke" class="underline">Einstellungen › Netzwerke</a> vergeben. Die MAC-Adresse kann schon erfasst werden.
        </p>
    <?php endif; ?>
    <div class="space-y-2">
        <?php foreach ($form->rows as $i => $row): ?>
            <?php
            $key = $row->slot->key;
            $name = 'network[' . $key . ']';
            $id = $formId . '-net-' . $i;
            $network = $form->network($row->networkId);
            ?>
            <div class="js-network-row flex flex-wrap items-end gap-2"
                 data-suggested="<?= $row->suggested ? '1' : '0' ?>">
                <div class="w-full pb-2 text-xs text-slate-700 dark:text-slate-300 sm:w-40">
                    <?= e($row->slot->label) ?>
                    <?php if ($row->slot->kind === NetworkInterfaceSlot::KIND_ORPHAN): ?>
                        <span class="block text-[11px] text-amber-700 dark:text-amber-400">Schnittstelle gibt es am Artikel nicht mehr – zum Entfernen Felder leeren.</span>
                    <?php elseif ($row->slot->optional): ?>
                        <span class="block text-[11px] text-slate-400 dark:text-slate-500">optional, falls verbaut</span>
                    <?php endif; ?>
                </div>
                <div class="w-40">
                    <label for="<?= e($id) ?>-network" class="block text-[11px] text-slate-500 dark:text-slate-400">Netzwerk</label>
                    <select id="<?= e($id) ?>-network" name="<?= e($name) ?>[network_id]" class="js-network-select <?= $input ?> font-sans">
                        <option value="">– keins –</option>
                        <?php foreach ($form->networks as $option): ?>
                            <option value="<?= e($option->id) ?>" <?= $option->id === $row->networkId ? 'selected' : '' ?>><?= e($option->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="js-network-v4 w-36 <?= $network !== null && !$network->supports(4) ? 'hidden' : '' ?>">
                    <label for="<?= e($id) ?>-ipv4" class="block text-[11px] text-slate-500 dark:text-slate-400">IPv4-Adresse</label>
                    <input id="<?= e($id) ?>-ipv4" name="<?= e($name) ?>[ipv4]" value="<?= e($row->ipv4) ?>" maxlength="15" inputmode="decimal" autocomplete="off"
                           placeholder="<?= e($network?->cidr(4) ?? 'z. B. 192.168.10.20') ?>" class="js-network-ip4 <?= $input ?>">
                </div>
                <div class="js-network-v6 w-56 <?= $network === null || !$network->supports(6) ? 'hidden' : '' ?>">
                    <label for="<?= e($id) ?>-ipv6" class="block text-[11px] text-slate-500 dark:text-slate-400">IPv6-Adresse</label>
                    <input id="<?= e($id) ?>-ipv6" name="<?= e($name) ?>[ipv6]" value="<?= e($row->ipv6) ?>" maxlength="39" autocomplete="off"
                           placeholder="<?= e($network?->cidr(6) ?? 'z. B. fd00:10::20') ?>" class="js-network-ip6 <?= $input ?>">
                </div>
                <div class="w-40">
                    <label for="<?= e($id) ?>-mac" class="block text-[11px] text-slate-500 dark:text-slate-400">MAC-Adresse</label>
                    <input id="<?= e($id) ?>-mac" name="<?= e($name) ?>[mac]" value="<?= e($row->mac) ?>" maxlength="17" autocomplete="off"
                           placeholder="00:1D:C1:12:34:56" class="<?= $input ?>">
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">
        Vorschlag: zuletzt vergebene Adresse im Bereich + 1. Eigene Adressen sind möglich, solange sie im Subnetz liegen und noch frei sind.
    </p>
</div>
