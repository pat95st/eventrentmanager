<?php
/**
 * Fields of a network (migration 155), used for creating and editing.
 *
 * @var \App\Domain\Network\Network|null $network
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

use App\Domain\Network\IpMode;

use function App\Infrastructure\Http\e;

$val = static fn (string $key, mixed $fallback): string => array_key_exists($key, $old) && is_string($old[$key]) ? $old[$key] : (string) ($fallback ?? '');
$cls = static fn (string $error): string => 'mt-1 w-full rounded-md border px-2 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:bg-slate-900 '
    . (isset($errors[$error]) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700');
$err = static fn (string $error): string => isset($errors[$error]) ? '<p class="mt-1 text-xs text-red-600 dark:text-red-400">' . e($errors[$error]) . '</p>' : '';
$labelCls = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$mode = $val('ip_mode', $network?->mode->value ?? IpMode::Ipv4->value);
$prefix = $network?->id ?? 'new';
?>
<div class="js-network-settings space-y-4">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_8rem_6rem]">
        <div>
            <label class="<?= $labelCls ?>" for="net-<?= e($prefix) ?>-name">Bezeichnung *</label>
            <input id="net-<?= e($prefix) ?>-name" name="name" required maxlength="100" value="<?= e($val('name', $network?->name)) ?>" placeholder="z. B. Dante Primary" class="<?= $cls('name') ?>">
            <?= $err('name') ?>
        </div>
        <div>
            <label class="<?= $labelCls ?>" for="net-<?= e($prefix) ?>-vlan">VLAN-ID</label>
            <input id="net-<?= e($prefix) ?>-vlan" name="vlan_id" type="number" min="1" max="4094" value="<?= e($val('vlan_id', $network?->vlanId)) ?>" placeholder="ohne" class="<?= $cls('vlanId') ?>">
            <?= $err('vlanId') ?>
        </div>
        <div>
            <label class="<?= $labelCls ?>" for="net-<?= e($prefix) ?>-sort">Reihenfolge</label>
            <input id="net-<?= e($prefix) ?>-sort" name="sort_order" type="number" value="<?= e($val('sort_order', $network?->sortOrder)) ?>" class="<?= $cls('sortOrder') ?>">
        </div>
    </div>

    <fieldset>
        <legend class="<?= $labelCls ?>">Adressfamilie *</legend>
        <div class="mt-1 flex flex-wrap gap-4 text-sm text-slate-700 dark:text-slate-300">
            <?php foreach (IpMode::cases() as $case): ?>
                <label class="flex items-center gap-1.5">
                    <input type="radio" name="ip_mode" value="<?= e($case->value) ?>" class="js-ip-mode" <?= $mode === $case->value ? 'checked' : '' ?>>
                    <?= e($case->label()) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?= $err('ipMode') ?>
    </fieldset>

    <fieldset class="js-ipv4-fields rounded-md border border-slate-200 p-3 dark:border-slate-800 <?= IpMode::tryFrom($mode)?->supports(4) ?? true ? '' : 'hidden' ?>">
        <legend class="px-1 text-xs font-semibold text-slate-700 dark:text-slate-300">IPv4</legend>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
                <label class="<?= $labelCls ?>" for="net-<?= e($prefix) ?>-v4">Netzwerkadresse *</label>
                <input id="net-<?= e($prefix) ?>-v4" name="ipv4_network" value="<?= e($val('ipv4_network', $network?->ipv4Network)) ?>" placeholder="z. B. 192.168.10.0 oder 192.168.10.0/24"
                       class="js-ipv4-network font-mono <?= $cls('ipv4Network') ?>">
                <?= $err('ipv4Network') ?>
            </div>
            <div>
                <label class="<?= $labelCls ?>" for="net-<?= e($prefix) ?>-mask">Subnetzmaske *</label>
                <input id="net-<?= e($prefix) ?>-mask" name="ipv4_mask" value="<?= e($val('ipv4_mask', $network?->ipv4Prefix !== null ? '/' . $network->ipv4Prefix : '')) ?>" placeholder="/24 oder 255.255.255.0"
                       class="js-ipv4-mask font-mono <?= $cls('ipv4Prefix') ?>">
                <p class="js-ipv4-mask-hint mt-1 font-mono text-[11px] text-slate-500 dark:text-slate-400"></p>
                <?= $err('ipv4Prefix') ?>
            </div>
        </div>
        <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">Nur private Bereiche: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16.</p>
    </fieldset>

    <fieldset class="js-ipv6-fields rounded-md border border-slate-200 p-3 dark:border-slate-800 <?= IpMode::tryFrom($mode)?->supports(6) ? '' : 'hidden' ?>">
        <legend class="px-1 text-xs font-semibold text-slate-700 dark:text-slate-300">IPv6</legend>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[2fr_1fr_1fr]">
            <div>
                <label class="<?= $labelCls ?>" for="net-<?= e($prefix) ?>-v6">Netzwerkadresse *</label>
                <input id="net-<?= e($prefix) ?>-v6" name="ipv6_network" value="<?= e($val('ipv6_network', $network?->ipv6Network)) ?>" placeholder="z. B. fd00:10:: oder fd00:10::/64"
                       class="font-mono <?= $cls('ipv6Network') ?>">
                <?= $err('ipv6Network') ?>
            </div>
            <div>
                <label class="<?= $labelCls ?>" for="net-<?= e($prefix) ?>-v6prefix">Präfixlänge *</label>
                <input id="net-<?= e($prefix) ?>-v6prefix" name="ipv6_prefix" value="<?= e($val('ipv6_prefix', $network?->ipv6Prefix !== null ? '/' . $network->ipv6Prefix : '')) ?>" placeholder="/64"
                       class="font-mono <?= $cls('ipv6Prefix') ?>">
                <?= $err('ipv6Prefix') ?>
            </div>
            <div>
                <label class="<?= $labelCls ?>" for="net-<?= e($prefix) ?>-v6default">Standard-Präfixlänge</label>
                <input id="net-<?= e($prefix) ?>-v6default" name="ipv6_default_prefix" value="<?= e($val('ipv6_default_prefix', $network?->ipv6DefaultPrefix !== null ? '/' . $network->ipv6DefaultPrefix : '')) ?>" placeholder="/64"
                       class="font-mono <?= $cls('ipv6DefaultPrefix') ?>">
                <?= $err('ipv6DefaultPrefix') ?>
            </div>
        </div>
        <p class="mt-2 text-[11px] text-slate-400 dark:text-slate-500">
            Nur Unique Local Addresses (fc00::/7, üblich fd00::/8). Die Standard-Präfixlänge gilt für die Adressen der Geräte (leer = /64).
        </p>
    </fieldset>

    <div>
        <label class="<?= $labelCls ?>" for="net-<?= e($prefix) ?>-notes">Notiz</label>
        <input id="net-<?= e($prefix) ?>-notes" name="notes" maxlength="255" value="<?= e($val('notes', $network?->notes)) ?>" placeholder="z. B. Gateway 192.168.10.1, kein DHCP" class="<?= $cls('notes') ?>">
        <?= $err('notes') ?>
    </div>
</div>
<script>
    (function () {
        var scripts = document.getElementsByTagName('script');
        var box = scripts[scripts.length - 1].previousElementSibling;
        if (!box || !box.classList.contains('js-network-settings')) { return; }
        var v4 = box.querySelector('.js-ipv4-fields');
        var v6 = box.querySelector('.js-ipv6-fields');
        var network = box.querySelector('.js-ipv4-network');
        var mask = box.querySelector('.js-ipv4-mask');
        var hint = box.querySelector('.js-ipv4-mask-hint');
        function syncMode() {
            var checked = box.querySelector('.js-ip-mode:checked');
            var mode = checked ? checked.value : 'ipv4';
            v4.classList.toggle('hidden', mode === 'ipv6');
            v6.classList.toggle('hidden', mode === 'ipv4');
        }
        function prefixToMask(prefix) {
            var parts = [];
            for (var i = 0; i < 4; i++) {
                var bits = Math.max(0, Math.min(8, prefix - i * 8));
                parts.push(bits === 0 ? 0 : (0xFF << (8 - bits)) & 0xFF);
            }
            return parts.join('.');
        }
        function maskToPrefix(text) {
            var parts = text.split('.');
            if (parts.length !== 4) { return null; }
            var bits = '';
            for (var i = 0; i < 4; i++) {
                var n = parseInt(parts[i], 10);
                if (isNaN(n) || n < 0 || n > 255 || String(n) !== parts[i]) { return null; }
                bits += ('00000000' + n.toString(2)).slice(-8);
            }
            return /^1*0*$/.test(bits) ? bits.indexOf('0') === -1 ? 32 : bits.indexOf('0') : null;
        }
        function syncHint() {
            var text = mask.value.trim();
            var cidr = network.value.split('/');
            if (text === '' && cidr.length === 2) { text = cidr[1]; }
            text = text.replace(/^\//, '');
            var prefix = /^\d{1,2}$/.test(text) ? parseInt(text, 10) : maskToPrefix(text);
            if (prefix === null || prefix > 32) { hint.textContent = ''; return; }
            var hosts = prefix >= 31 ? 0 : Math.pow(2, 32 - prefix) - 2;
            hint.textContent = '/' + prefix + ' = ' + prefixToMask(prefix) + ' · ' + hosts.toLocaleString('de-DE') + ' Adressen nutzbar';
        }
        box.querySelectorAll('.js-ip-mode').forEach(function (radio) { radio.addEventListener('change', syncMode); });
        mask.addEventListener('input', syncHint);
        network.addEventListener('input', syncHint);
        syncMode();
        syncHint();
    })();
</script>
