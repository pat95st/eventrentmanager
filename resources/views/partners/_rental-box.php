<?php
/**
 * Partner box on the event page (migration 157).
 *
 * @var \App\Domain\Rental\Rental $rental
 * @var \App\Application\Federation\Query\RentalPartnerRequests $partnerRequests
 */

use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\e;

if ($partnerRequests->activePartners === [] && $partnerRequests->requests === []) {
    return;
}
?>
<section class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Partner</h2>
    <?php if ($partnerRequests->activePartners !== []): ?>
        <div class="<?= $partnerRequests->requests !== [] ? 'mb-3 ' : '' ?>flex flex-wrap gap-2 text-sm">
            <?php foreach ($partnerRequests->activePartners as $partner): ?>
                <span class="inline-flex items-center gap-2 rounded-md border border-slate-200 px-2 py-1 dark:border-slate-700">
                    <span class="text-slate-700 dark:text-slate-300"><?= e($partner->name) ?>:</span>
                    <a class="text-brand-700 hover:underline dark:text-brand-400" href="/partner/<?= e($partner->id) ?>/materialpool?rental=<?= e($rental->id) ?>">Material</a>
                    <a class="text-brand-700 hover:underline dark:text-brand-400" href="/partner/<?= e($partner->id) ?>/techniker?rental=<?= e($rental->id) ?>">Techniker</a>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($partnerRequests->requests !== []): ?>
        <?= View::render('partners/_request-list', ['requests' => $partnerRequests->requests, 'showPartner' => true]) ?>
    <?php endif; ?>
</section>
