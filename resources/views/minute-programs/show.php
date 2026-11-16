<?php
/** @var \App\Application\MinuteProgram\Query\MinuteProgramDetail $detail */
/** @var \App\Domain\Rental\Rental $rental */
/** @var \App\Domain\MinuteProgram\MinuteProgramShareLink[] $shareLinks */

use App\Infrastructure\Http\Csrf;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$program = $detail->program;
$trades = $detail->trades; // migration 171
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/veranstaltungen/<?= e($rental->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('minute_programs.show.back')) ?></a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($program->name) ?></h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            <?= e($rental->eventName) ?>
            <?php if ($program->room !== null): ?> · <?= e($program->room) ?><?php endif; ?>
            · <?= e($program->startAt->format('d.m.Y H:i')) ?>
        </p>
        <p class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
            <span><?= e(t($program->tradeIds === null ? 'minute_programs.show.trades_auto' : 'minute_programs.show.trades')) ?>:</span>
            <?php foreach ($trades as $trade): ?>
                <span class="rounded px-1.5 py-0.5 font-semibold text-white" style="background: <?= e($trade->color) ?>"><?= e($trade->name) ?></span>
            <?php endforeach; ?>
            <?php if ($trades === []): ?><span>–</span><?php endif; ?>
        </p>
        <?php if ($program->jingleBefore): ?>
            <?php $jingleTitle = null; foreach (($jingles ?? []) as $j) { if ($j->id === $program->jingleAudioFileId) { $jingleTitle = $j->title; } } ?>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">🎵 Jingle vorher: <?= $jingleTitle !== null ? e($jingleTitle) : 'ja (noch keine Datei gewählt)' ?></p>
        <?php endif; ?>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="/minutenprogramme/<?= e($program->id) ?>/foh" title="<?= e(t('minute_programs.show.foh_hint')) ?>"
           class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('minute_programs.show.foh')) ?></a>
        <a href="/minutenprogramme/<?= e($program->id) ?>/vollbild" target="_blank"
           class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('minute_programs.show.fullscreen')) ?></a>
        <a href="/minutenprogramme/<?= e($program->id) ?>/bearbeiten"
           class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.edit')) ?></a>
        <form method="post" action="/minutenprogramme/<?= e($program->id) ?>/loeschen" onsubmit="return confirm('<?= e(t('minute_programs.show.delete_confirm')) ?>')">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('common.delete')) ?></button>
        </form>
    </div>
</div>

<?php if ($rental->noStrobe): /* Migration 166 */ ?>
<div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
    <?= e(t('minute_programs.show.no_strobe_event')) ?>
</div>
<?php endif; ?>

<!-- Live-Verzögerung -->
<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <form method="post" action="/minutenprogramme/<?= e($program->id) ?>/verzoegerung" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
        <div>
            <label for="current_delay_minutes" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_programs.show.delay')) ?></label>
            <input type="number" id="current_delay_minutes" name="current_delay_minutes" value="<?= e((string) $program->delayMinutes()) ?>"
                   class="mt-1 w-28 rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
        </div>
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('minute_programs.show.delay_apply')) ?></button>
        <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('minute_programs.show.delay_hint')) ?></p>
    </form>
</div>

<!-- Programmpunkte -->
<div class="mb-6 overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <table class="w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">
            <tr>
                <th class="px-4 py-2"><?= e(t('minute_programs.show.col_time')) ?></th>
                <th class="px-4 py-2"><?= e(t('minute_programs.show.col_name')) ?></th>
                <th class="px-4 py-2"><?= e(t('minute_programs.show.col_duration')) ?></th>
                <th class="px-4 py-2"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($detail->items as $itemDetail): $item = $itemDetail->item; ?>
            <tr class="<?= $itemDetail->isCurrent ? 'bg-amber-50 dark:bg-amber-950/30' : '' ?>">
                <td class="px-4 py-2 align-top font-mono text-xs"><?= e($itemDetail->currentStart->format('H:i')) ?></td>
                <td class="px-4 py-2 align-top">
                    <div class="font-medium text-slate-900 dark:text-slate-100"><?= e($item->name) ?><?= $item->noStrobe ? ' <span class="ml-1 rounded bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-red-700 dark:bg-red-950/40 dark:text-red-400">' . e(t('minute_programs.show.no_strobe')) . '</span>' : '' ?><?= $itemDetail->isNext ? ' <span class="ml-1 rounded bg-brand-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-brand-700 dark:bg-brand-950/40 dark:text-brand-400">' . e(t('minute_programs.show.next')) . '</span>' : '' ?></div>
                    <?php if ($item->description !== null): ?><div class="text-xs text-slate-500 dark:text-slate-400"><?= e($item->description) ?></div><?php endif; ?>
                    <?php if ($item->noteGeneral !== null): ?>
                        <div class="mt-1 text-xs text-slate-500 dark:text-slate-400"><span class="font-semibold"><?= e(t('minute_programs.show.note_general')) ?>:</span> <?= e($item->noteGeneral) ?></div>
                    <?php endif; ?>
                    <?php foreach ($trades as $trade): if (!isset($item->tradeNotes[$trade->id])) { continue; } ?>
                        <div class="mt-1 border-l-4 pl-2 text-xs text-slate-600 dark:text-slate-300" style="border-color: <?= e($trade->color) ?>">
                            <span class="font-semibold" style="color: <?= e($trade->color) ?>"><?= e($trade->name) ?>:</span> <?= e($item->tradeNotes[$trade->id]) ?>
                        </div>
                    <?php endforeach; ?>
                </td>
                <td class="px-4 py-2 align-top text-xs text-slate-500"><?= $item->durationMinutes !== null ? e((string) $item->durationMinutes) . ' min' : '–' ?></td>
                <td class="px-4 py-2 align-top text-right">
                    <div class="flex items-center justify-end gap-1">
                        <form method="post" action="/minutenprogramm-punkte/<?= e($item->id) ?>/verschieben">
                            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                            <input type="hidden" name="minute_program_id" value="<?= e($program->id) ?>">
                            <input type="hidden" name="direction" value="up">
                            <button type="submit" class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">↑</button>
                        </form>
                        <form method="post" action="/minutenprogramm-punkte/<?= e($item->id) ?>/verschieben">
                            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                            <input type="hidden" name="minute_program_id" value="<?= e($program->id) ?>">
                            <input type="hidden" name="direction" value="down">
                            <button type="submit" class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300">↓</button>
                        </form>
                        <button type="button" onclick="document.getElementById('edit-item-<?= e($item->id) ?>').classList.toggle('hidden')"
                                class="rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300"><?= e(t('common.edit')) ?></button>
                        <form method="post" action="/minutenprogramm-punkte/<?= e($item->id) ?>/loeschen" onsubmit="return confirm('<?= e(t('minute_programs.show.delete_item_confirm')) ?>')">
                            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                            <input type="hidden" name="minute_program_id" value="<?= e($program->id) ?>">
                            <button type="submit" class="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400">×</button>
                        </form>
                    </div>
                </td>
            </tr>
            <tr id="edit-item-<?= e($item->id) ?>" class="hidden bg-slate-50 dark:bg-slate-800/50">
                <td colspan="4" class="px-4 py-3">
                    <form method="post" action="/minutenprogramm-punkte/<?= e($item->id) ?>" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                        <input type="hidden" name="minute_program_id" value="<?= e($program->id) ?>">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_programs.item_form.name')) ?></label>
                            <input type="text" name="name" required value="<?= e($item->name) ?>" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_programs.item_form.duration')) ?></label>
                            <input type="number" min="0" name="duration_minutes" value="<?= e((string) ($item->durationMinutes ?? '')) ?>" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_programs.item_form.description')) ?></label>
                            <input type="text" name="description" value="<?= e($item->description ?? '') ?>" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('minute_programs.item_form.note_general')) ?></label>
                            <textarea name="note_general" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700"><?= e($item->noteGeneral ?? '') ?></textarea>
                        </div>
                        <?php foreach ($trades as $trade): ?>
                        <div>
                            <label class="flex items-center gap-1.5 text-xs font-medium text-slate-600 dark:text-slate-400">
                                <span class="inline-block h-2.5 w-2.5 rounded-full" style="background: <?= e($trade->color) ?>"></span>
                                <?= e(t('minute_programs.item_form.note_trade', ['trade' => $trade->name])) ?>
                            </label>
                            <textarea name="trade_notes[<?= e($trade->id) ?>]" rows="2" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-1.5 text-sm dark:border-slate-700" style="border-left: 4px solid <?= e($trade->color) ?>"><?= e($item->tradeNotes[$trade->id] ?? '') ?></textarea>
                        </div>
                        <?php endforeach; ?>
                        <?php if ($trades === []): ?>
                        <p class="text-xs text-slate-500 sm:col-span-2 dark:text-slate-400"><?= e(t('minute_programs.item_form.no_trades')) ?></p>
                        <?php endif; ?>
                        <div class="sm:col-span-2">
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                                <input type="checkbox" name="no_strobe" value="1" <?= $item->noStrobe ? 'checked' : '' ?>>
                                <?= e(t('minute_programs.item_form.no_strobe')) ?>
                            </label>
                        </div>
                        <div class="sm:col-span-2">
                            <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('common.save')) ?></button>
                        </div>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="border-t border-slate-200 p-4 dark:border-slate-800">
        <form method="post" action="/minutenprogramme/<?= e($program->id) ?>/punkte" class="grid grid-cols-1 gap-3 sm:grid-cols-4">
            <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
            <input type="text" name="name" required placeholder="<?= e(t('minute_programs.item_form.name')) ?>" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
            <input type="number" min="0" name="duration_minutes" placeholder="<?= e(t('minute_programs.item_form.duration')) ?>" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
            <input type="text" name="description" placeholder="<?= e(t('minute_programs.item_form.description')) ?>" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('minute_programs.show.add_item')) ?></button>
            <label class="flex items-center gap-2 text-sm text-slate-700 sm:col-span-4 dark:text-slate-300">
                <input type="checkbox" name="no_strobe" value="1">
                <?= e(t('minute_programs.item_form.no_strobe')) ?>
            </label>
        </form>
    </div>
</div>

<!-- Als Vorlage speichern -->
<div class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-2 text-sm font-semibold text-slate-900 dark:text-slate-100"><?= e(t('minute_programs.show.save_as_template')) ?></h2>
    <form method="post" action="/minutenprogramme/<?= e($program->id) ?>/als-vorlage-speichern" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
        <input type="text" name="template_name" required placeholder="<?= e(t('minute_programs.show.template_name_placeholder')) ?>" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.save')) ?></button>
    </form>
</div>

<!-- Freigabe-Links -->
<div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-slate-100"><?= e(t('minute_programs.show.share_links')) ?></h2>

    <?php if ($shareLinks !== []): ?>
    <ul class="mb-4 space-y-2">
        <?php foreach ($shareLinks as $link): ?>
            <li class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-slate-200 p-2 text-xs dark:border-slate-800">
                <div>
                    <span class="font-mono"><?= e($link->isActive() ? '/minutenprogramm/freigabe/' . $link->token : t('minute_programs.show.share_link_revoked')) ?></span>
                    <span class="ml-2 rounded bg-slate-100 px-1.5 py-0.5 dark:bg-slate-800"><?= e($link->mode->label()) ?></span>
                    <?php if ($link->isActive()): ?>
                        <div class="mt-1 text-slate-500 dark:text-slate-400">
                            <a class="hover:underline" href="/minutenprogramm/freigabe/<?= e($link->token) ?>/foh" target="_blank"><?= e(t('minute_programs.show.foh')) ?></a>
                            · <?= e(t('minute_programs.show.stage_api')) ?>: <span class="font-mono">/api/runsheet/v1/<?= e($link->token) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($link->isActive()): ?>
                <form method="post" action="/freigabe-links/<?= e($link->id) ?>/widerrufen">
                    <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
                    <input type="hidden" name="minute_program_id" value="<?= e($program->id) ?>">
                    <button type="submit" class="rounded-md border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400"><?= e(t('minute_programs.show.revoke')) ?></button>
                </form>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <form method="post" action="/minutenprogramme/<?= e($program->id) ?>/freigabe-links" class="flex flex-wrap items-end gap-3">
        <input type="hidden" name="csrf_token" value="<?= e(Csrf::token()) ?>">
        <select name="mode" class="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700">
            <option value="read"><?= e(t('minute_programs.show.mode_read')) ?></option>
            <option value="read_write"><?= e(t('minute_programs.show.mode_read_write')) ?></option>
        </select>
        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('minute_programs.show.share_link_create')) ?></button>
    </form>
</div>
