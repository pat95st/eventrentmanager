<?php
/**
 * Schema eines Stromverteilers (Migration 117): Zuleitung, darunter direkte
 * Abgänge, RCDs mit ihren Abgängen und nachgeschalteten LS, FI/LS-
 * Kombinationen und LS ohne FI.
 *
 * @var \App\Domain\PowerDistributor\PowerDistributor $distributor
 */

use App\Domain\PowerDistributor\ProtectiveDeviceType;
use function App\Infrastructure\Http\e;

$outletsByDevice = [];
foreach ($distributor->outlets as $outlet) {
    $outletsByDevice[$outlet->deviceId ?? ''][] = $outlet;
}
$downstream = [];
foreach ($distributor->devices as $device) {
    if ($device->upstreamDeviceId !== null) {
        $downstream[$device->upstreamDeviceId][] = $device;
    }
}

$renderOutlets = static function (array $outlets) use ($distributor): void {
    foreach ($outlets as $outlet) {
        $phase = $distributor->phaseOf($outlet);
        ?>
        <li class="flex items-center gap-2 py-0.5 text-sm text-slate-700 dark:text-slate-300">
            <span class="inline-block rounded-full" style="width:.6rem;height:.6rem;background:<?= e($outlet->connectorType->color()) ?>"></span>
            <span class="font-medium"><?= (int) $outlet->quantity ?>×</span>
            <span><?= e($outlet->connectorType->label()) ?></span>
            <?php if ($outlet->label !== null): ?><span class="text-xs text-slate-500 dark:text-slate-400">(<?= e($outlet->label) ?>)</span><?php endif; ?>
            <?php if ($phase !== null): ?><span class="rounded bg-slate-100 px-1 font-mono text-[11px] text-slate-600 dark:bg-slate-800 dark:text-slate-300"><?= e($phase) ?></span><?php endif; ?>
        </li>
        <?php
    }
};

$renderDevice = static function ($device, string $note = '') use (&$renderDevice, $outletsByDevice, $downstream, $renderOutlets): void {
    $badge = match ($device->type) {
        ProtectiveDeviceType::Rcd => 'background:#fef3c7;color:#92400e',
        ProtectiveDeviceType::Mcb => 'background:#e0e7ff;color:#3730a3',
        ProtectiveDeviceType::Rcbo => 'background:#dcfce7;color:#166534',
        ProtectiveDeviceType::Fuse => 'background:#fce7f3;color:#9d174d',
    };
    ?>
    <li class="py-1">
        <div class="flex flex-wrap items-center gap-2 text-sm">
            <span class="rounded px-1.5 py-0.5 text-xs font-semibold" style="<?= $badge ?>"><?= e($device->label) ?></span>
            <span class="text-slate-800 dark:text-slate-200"><?= e($device->specification()) ?></span>
            <?php if ($note !== ''): ?><span class="text-xs text-slate-500 dark:text-slate-400"><?= e($note) ?></span><?php endif; ?>
        </div>
        <?php $children = $downstream[$device->id] ?? []; $own = $outletsByDevice[$device->id] ?? []; ?>
        <?php if ($children !== [] || $own !== []): ?>
            <ul style="margin-left:.75rem;border-left:1px solid rgba(148,163,184,.55);padding-left:1rem">
                <?php $renderOutlets($own); ?>
                <?php foreach ($children as $child): $renderDevice($child); endforeach; ?>
            </ul>
        <?php elseif ($device->type !== ProtectiveDeviceType::Rcd): ?>
            <p style="margin-left:1.75rem" class="text-xs text-slate-400 dark:text-slate-500">keine Abgänge zugeordnet</p>
        <?php endif; ?>
    </li>
    <?php
};
?>
<div class="text-sm">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Zuleitung</span>
        <span class="inline-block rounded-full" style="width:.7rem;height:.7rem;background:<?= e($distributor->inletType->color()) ?>"></span>
        <span class="font-semibold text-slate-900 dark:text-slate-100"><?= e($distributor->inletType->label()) ?></span>
    </div>
    <ul class="mt-1" style="margin-left:.3rem;border-left:2px solid rgba(100,116,139,.6);padding-left:1rem">
        <?php if (($outletsByDevice[''] ?? []) !== []): ?>
            <li class="py-1">
                <p class="text-xs font-medium text-red-700 dark:text-red-400">direkt von der Zuleitung (ohne Schutzorgan)</p>
                <ul style="margin-left:.75rem;border-left:1px solid rgba(148,163,184,.55);padding-left:1rem"><?php $renderOutlets($outletsByDevice['']); ?></ul>
            </li>
        <?php endif; ?>
        <?php foreach ($distributor->devices as $device): ?>
            <?php if ($device->upstreamDeviceId === null): ?>
                <?php $renderDevice($device, in_array($device->type, [ProtectiveDeviceType::Mcb, ProtectiveDeviceType::Fuse], true) ? 'ohne FI' : ''); ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($distributor->devices === [] && $distributor->outlets === []): ?>
            <li class="py-1 text-xs text-slate-400 dark:text-slate-500">Noch keine Schutzorgane oder Abgänge erfasst.</li>
        <?php endif; ?>
    </ul>
    <?php if ($distributor->hasFuses()): ?>
        <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">
            Schmelzsicherungen verbaut – beim Verleih Ersatzsicherungen mitgeben:
            <?= e(implode(', ', array_map(static fn ($name, $count) => $count . ' × ' . $name, array_keys($distributor->fuseSummary()), $distributor->fuseSummary()))) ?>.
        </p>
    <?php endif; ?>
    <?php if ($distributor->notes !== null): ?>
        <p class="mt-3 whitespace-pre-line text-xs text-slate-500 dark:text-slate-400"><?= e($distributor->notes) ?></p>
    <?php endif; ?>
</div>
