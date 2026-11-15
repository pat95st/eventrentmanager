<?php
/**
 * @var array{name: string, baseUrl: string, link: string, profile: \App\Domain\Federation\PartnerProfile} $preview
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
?>
<div class="mb-6">
    <a href="/einstellungen/partner" class="text-xs text-brand-700 hover:underline dark:text-brand-400">← Partner-Instanzen</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Partnerschaft mit <?= e($preview['name']) ?></h1>
    <p class="font-mono text-sm text-slate-500 dark:text-slate-400"><?= e($preview['baseUrl']) ?></p>
    <?php if ($preview['profile']->addressLine() !== ''): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($preview['profile']->addressLine()) ?></p>
    <?php endif; ?>
</div>

<?php if (isset($errors['link']) || isset($errors['partner'])): ?>
    <p class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800"><?= e($errors['link'] ?? $errors['partner']) ?></p>
<?php endif; ?>

<form method="post" action="/einstellungen/partner/verbinden/bestaetigen" class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="link" value="<?= e($preview['link']) ?>">
    <?= View::render('settings/_partner-agreement-fields', [
        'errors' => $errors,
        'old' => $old,
        'granted' => 0.0,
        'received' => 0.0,
        'withSettings' => true,
        'autoAccept' => false,
        'allowTechnicians' => true,
        'partnerName' => $preview['name'],
    ]) ?>
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <p class="text-xs text-slate-500 dark:text-slate-400"><?= e($preview['name']) ?> muss die Partnerschaft danach noch bestätigen.</p>
        <button type="submit" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">Partnerschaft anfragen</button>
    </div>
</form>
