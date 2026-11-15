<?php
/**
 * @var \App\Domain\Federation\PartnerInstance[] $partners
 * @var \App\Application\Federation\Query\PartnerRequestView[] $requests
 * @var bool $isAdmin
 */

use App\Domain\Federation\Discount;
use App\Domain\Federation\PartnerRequestStatus;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\e;

$card = 'rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900';
$open = array_values(array_filter($requests, static fn ($v) => $v->request->isIncoming() && $v->request->status === PartnerRequestStatus::Pending));
$others = array_values(array_filter($requests, static fn ($v) => !($v->request->isIncoming() && $v->request->status === PartnerRequestStatus::Pending)));
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Partner</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Material und Techniker bei befreundeten Firmen anfragen und deren Anfragen beantworten.</p>
    </div>
    <?php if ($isAdmin): ?>
        <a href="/einstellungen/partner" class="text-sm text-brand-700 hover:underline dark:text-brand-400">Partner verwalten →</a>
    <?php endif; ?>
</div>

<div class="space-y-6">
    <?php if ($partners === []): ?>
        <p class="<?= $card ?> text-sm text-slate-500 dark:text-slate-400">
            Noch keine aktiven Partner.<?= $isAdmin ? ' Unter Einstellungen › Partner-Instanzen kannst du einen Einladungslink erstellen oder den Link eines Partners eingeben.' : ' Ein Administrator kann Partner unter Einstellungen › Partner-Instanzen hinzufügen.' ?>
        </p>
    <?php else: ?>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <?php foreach ($partners as $partner): ?>
                <div class="<?= $card ?>">
                    <p class="font-medium text-slate-800 dark:text-slate-200"><?= e($partner->name) ?></p>
                    <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
                        <?= $partner->profile->city !== null ? e($partner->profile->city) . ' · ' : '' ?>Rabatt für uns: <?= e(Discount::format($partner->discountReceived)) ?>
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <a href="/partner/<?= e($partner->id) ?>/materialpool" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Materialpool</a>
                        <a href="/partner/<?= e($partner->id) ?>/techniker" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Techniker anfragen</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($open !== []): ?>
        <section class="rounded-lg border border-amber-300 bg-white p-4 dark:border-amber-800 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-semibold text-amber-800 dark:text-amber-300">Offene Anfragen von Partnern (<?= count($open) ?>)</h2>
            <?= View::render('partners/_request-list', ['requests' => $open, 'showPartner' => true]) ?>
        </section>
    <?php endif; ?>

    <section class="<?= $card ?>">
        <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Alle Anfragen</h2>
        <?= View::render('partners/_request-list', ['requests' => $others, 'showPartner' => true]) ?>
    </section>
</div>
