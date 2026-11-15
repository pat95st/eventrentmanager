<?php
/**
 * @var \App\Application\Network\Query\NetworkSettings $settings
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Netzwerke</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Dokumentiert, welche IP-Adressen die Geräte haben dürfen. Je Netzwerk IPv4 und/oder IPv6 (nur private Bereiche) und optional die VLAN-ID.
        Kategorien können einen festen Adressbereich bekommen. Beim Anlegen von Inventarnummern für Artikel mit Netzwerkschnittstelle bzw.
        IP-basiertem Audioprotokoll (z. B. Dante) wird die nächste freie Adresse vorgeschlagen.
    </p>
</div>

<section class="space-y-4">
    <div class="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
        <?php if ($settings->networks === []): ?>
            <p class="p-4 text-sm text-slate-500 dark:text-slate-400">Noch keine Netzwerke angelegt.</p>
        <?php endif; ?>
        <?php foreach ($settings->networks as $network): ?>
            <?php $used = $settings->assignmentCounts[$network->id] ?? 0; $ranges = $settings->rangeCounts[$network->id] ?? 0; ?>
            <a href="/einstellungen/netzwerke/<?= e($network->id) ?>" class="flex flex-wrap items-center justify-between gap-2 p-3 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($network->name) ?></p>
                    <p class="font-mono text-xs text-slate-500 dark:text-slate-400">
                        <?= e($network->summary()) ?>
                        <?php if ($network->mode->supports(4) && $network->ipv4SubnetMask() !== null): ?>
                            <span class="font-sans">· Maske</span> <?= e($network->ipv4SubnetMask()) ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($network->notes !== null): ?>
                        <p class="text-xs text-slate-400 dark:text-slate-500"><?= e($network->notes) ?></p>
                    <?php endif; ?>
                </div>
                <span class="text-xs text-slate-500 dark:text-slate-400">
                    <?= e($network->mode->label()) ?> · <?= $used ?> Adresse<?= $used === 1 ? '' : 'n' ?> vergeben<?php if ($ranges > 0): ?> · <?= $ranges ?> Kategorie<?= $ranges === 1 ? '' : 'n' ?><?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="post" action="/einstellungen/netzwerke" class="rounded-lg border border-dashed border-slate-300 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Neues Netzwerk</h2>
        <?= View::render('settings/_network-fields', ['network' => null, 'errors' => $errors, 'old' => $old]) ?>
        <div class="mt-4 text-right">
            <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Netzwerk anlegen</button>
        </div>
    </form>
</section>
