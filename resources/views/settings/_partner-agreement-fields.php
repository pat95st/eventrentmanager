<?php
/**
 * Discount agreement (+ optionally the local settings) for partner forms.
 *
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 * @var float $granted current/proposed discount we grant
 * @var float $received current/proposed discount we receive
 * @var bool $withSettings show auto-accept / technician checkboxes
 * @var bool $autoAccept
 * @var bool $allowTechnicians
 * @var string $partnerName
 */

use function App\Infrastructure\Http\e;

$fmt = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',');
// Posted form: checkbox state; otherwise symmetric when both values are equal (default).
$symmetric = $old !== [] ? ($old['symmetric'] ?? '') === '1' : abs($granted - $received) < 0.005;
$input = 'mt-1 w-28 rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900';
$labelCls = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$uid = 'pa' . substr(md5((string) random_int(0, PHP_INT_MAX)), 0, 6);
?>
<div class="space-y-4" id="<?= e($uid) ?>">
    <?php if ($withSettings): ?>
        <fieldset class="space-y-2">
            <legend class="text-sm font-semibold text-slate-700 dark:text-slate-300">Anfragen von <?= e($partnerName) ?></legend>
            <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="auto_accept" value="1" class="mt-0.5" <?= ($old !== [] ? ($old['auto_accept'] ?? '') === '1' : $autoAccept) ? 'checked' : '' ?>>
                <span>Material-Anfragen automatisch eintragen und zusagen<br>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Ist alles im Zeitraum verfügbar, wird sofort eine Veranstaltung für den Partner angelegt und zugesagt. Sonst bleibt die Anfrage zur manuellen Prüfung offen.</span></span>
            </label>
            <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="allow_technicians" value="1" class="mt-0.5" <?= ($old !== [] ? ($old['allow_technicians'] ?? '') === '1' : $allowTechnicians) ? 'checked' : '' ?>>
                <span>Partner darf unsere Techniker sehen und anfragen<br>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Geteilt werden nur Namen der Techniker und die Gewerke. Techniker-Anfragen werden immer manuell zugesagt.</span></span>
            </label>
        </fieldset>
    <?php endif; ?>

    <fieldset class="space-y-2">
        <legend class="text-sm font-semibold text-slate-700 dark:text-slate-300">Rabatt auf Mietpreise</legend>
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="symmetric" value="1" data-symmetric-toggle <?= $symmetric ? 'checked' : '' ?>>
            Gleicher Rabatt in beide Richtungen
        </label>
        <div data-symmetric-on class="<?= $symmetric ? '' : 'hidden' ?>">
            <label class="<?= $labelCls ?>">Rabatt (%)
                <input type="text" inputmode="decimal" name="discount" value="<?= e((string) ($old['discount'] ?? $fmt($granted))) ?>" class="<?= $input ?>">
            </label>
        </div>
        <div data-symmetric-off class="<?= $symmetric ? 'hidden' : '' ?> flex flex-wrap gap-4">
            <label class="<?= $labelCls ?>">Wir gewähren <?= e($partnerName) ?> (%)
                <input type="text" inputmode="decimal" name="discount_granted" value="<?= e((string) ($old['discount_granted'] ?? $fmt($granted))) ?>" class="<?= $input ?> block">
            </label>
            <label class="<?= $labelCls ?>"><?= e($partnerName) ?> gewährt uns (%)
                <input type="text" inputmode="decimal" name="discount_received" value="<?= e((string) ($old['discount_received'] ?? $fmt($received))) ?>" class="<?= $input ?> block">
            </label>
        </div>
        <?php foreach (['discount', 'discountGranted', 'discountReceived'] as $key): ?>
            <?php if (isset($errors[$key])): ?><p class="text-xs text-red-600 dark:text-red-400"><?= e($errors[$key]) ?></p><?php endif; ?>
        <?php endforeach; ?>
        <p class="text-xs text-slate-500 dark:text-slate-400">Unterschiedliche Rabatte (oder spätere Änderungen) gelten erst, wenn beide Seiten zugestimmt haben.</p>
    </fieldset>
</div>
<script>
(function () {
    var root = document.getElementById(<?= json_encode($uid) ?>);
    var toggle = root.querySelector('[data-symmetric-toggle]');
    toggle.addEventListener('change', function () {
        root.querySelector('[data-symmetric-on]').classList.toggle('hidden', !toggle.checked);
        root.querySelector('[data-symmetric-off]').classList.toggle('hidden', toggle.checked);
    });
})();
</script>
