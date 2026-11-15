<?php
/**
 * @var \App\Application\Federation\Query\PartnerRequestView[] $requests
 * @var bool $showPartner
 */

use function App\Infrastructure\Http\e;

?>
<?php if ($requests === []): ?>
    <p class="text-sm text-slate-500 dark:text-slate-400">Noch keine Anfragen.</p>
<?php else: ?>
    <div class="divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($requests as $view): ?>
            <?php $r = $view->request; ?>
            <a href="/partner/anfragen/<?= e($r->id) ?>" class="flex flex-wrap items-center justify-between gap-2 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-800 dark:text-slate-200">
                        <span class="text-xs font-normal text-slate-500 dark:text-slate-400"><?= $r->isIncoming() ? '← von' : '→ an' ?><?= $showPartner ? ' ' . e($view->partnerName) : '' ?> ·</span>
                        <?= e($r->eventName) ?>
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        <?= e($r->kind->label()) ?> ·
                        <?= e($r->startDate->format('d.m.Y')) ?><?= $r->endDate != $r->startDate ? ' – ' . e($r->endDate->format('d.m.Y')) : '' ?> ·
                        <?php if ($r->kind === \App\Domain\Federation\PartnerRequestKind::Material): ?>
                            <?= count($r->items) ?> Artikel, <?= $r->totalQuantity() ?> Stück
                        <?php else: ?>
                            <?= $r->technicianCount ?> Techniker<?= $r->trades !== [] ? ' (' . e(implode(', ', $r->trades)) . ')' : '' ?>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <?php if ($r->syncError !== null): ?><span class="text-amber-700 dark:text-amber-400" title="<?= e($r->syncError) ?>">⚠</span><?php endif; ?>
                    <span class="rounded px-1.5 py-0.5" style="<?= e($r->status->badgeStyle()) ?>"><?= e($r->status->label()) ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
