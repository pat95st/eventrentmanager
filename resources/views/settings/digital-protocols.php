<?php
/** @var \App\Application\DigitalStagebox\Query\DigitalStageboxSettings $settings */

use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
$input = 'mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900';
$btn = 'rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Digitale Protokolle</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Digitale Audioprotokolle für Artikel der Kategorie „Digitale Stagebox“. Jede digitale Stagebox bekommt im Artikelformular
        eines dieser Protokolle zugeordnet. Protokolle, die von Stageboxen verwendet werden, lassen sich umbenennen, aber nicht löschen.
    </p>
</div>

<section id="protokolle" class="space-y-3">
    <div class="divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
        <?php if ($settings->protocols === []): ?>
            <p class="p-4 text-sm text-slate-500 dark:text-slate-400">Noch keine digitalen Protokolle angelegt.</p>
        <?php endif; ?>
        <?php foreach ($settings->protocols as $protocol): ?>
            <?php $used = $settings->usage[$protocol->id] ?? 0; ?>
            <div class="flex flex-wrap items-end gap-3 p-3">
                <form method="post" action="/einstellungen/digitale-protokolle/<?= e($protocol->id) ?>" class="flex min-w-0 flex-1 flex-wrap items-end gap-3">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <div class="min-w-0 flex-1">
                        <label class="sr-only" for="protocol-<?= e($protocol->id) ?>">Bezeichnung</label>
                        <input id="protocol-<?= e($protocol->id) ?>" name="name" required maxlength="100" value="<?= e($protocol->name) ?>" class="<?= $input ?>">
                    </div>
                    <input name="sort_order" type="number" value="<?= e((string) $protocol->sortOrder) ?>" class="<?= $input ?>" style="width:5.5rem" aria-label="Reihenfolge">
                    <button type="submit" class="<?= $btn ?>">Speichern</button>
                </form>
                <span class="text-xs text-slate-500 dark:text-slate-400" style="min-width:5.5rem"><?= $used ?> Stagebox<?= $used === 1 ? '' : 'en' ?></span>
                <?php if ($used === 0): ?>
                    <form method="post" action="/einstellungen/digitale-protokolle/<?= e($protocol->id) ?>/loeschen" data-confirm="Digitales Protokoll „<?= e($protocol->name) ?>“ löschen?" data-confirm-style="danger">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="post" action="/einstellungen/digitale-protokolle" class="rounded-lg border border-dashed border-slate-300 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Neues digitales Protokoll</h2>
        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-0 flex-1">
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="protocol_name_new">Bezeichnung *</label>
                <input id="protocol_name_new" name="name" required maxlength="100" placeholder="z. B. SoundGrid" class="<?= $input ?>">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400" for="protocol_sort_new">Reihenfolge</label>
                <input id="protocol_sort_new" name="sort_order" type="number" class="<?= $input ?>" style="width:5.5rem">
            </div>
        </div>
        <div class="mt-4 text-right">
            <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Protokoll anlegen</button>
        </div>
    </form>
</section>
