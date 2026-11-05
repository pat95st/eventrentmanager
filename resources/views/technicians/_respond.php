<?php
/**
 * Zu-/Absage-Buttons eines Technikers (Migration 134).
 *
 * @var \App\Domain\Technician\RentalTechnician $respondAssignment
 * @var string $respondBack 'liste' | 'detail'
 */

use App\Domain\Technician\RentalTechnicianStatus;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

if ($respondAssignment->status === RentalTechnicianStatus::Assigned) {
    return;
}
$respondCsrf = e(Csrf::token());
?>
<div class="flex flex-wrap items-center gap-2">
    <?php if ($respondAssignment->status !== RentalTechnicianStatus::Confirmed): ?>
        <form method="post" action="/veranstaltungs-techniker/<?= e($respondAssignment->id) ?>/antwort">
            <input type="hidden" name="csrf_token" value="<?= $respondCsrf ?>">
            <input type="hidden" name="answer" value="zusagen">
            <input type="hidden" name="back" value="<?= e($respondBack) ?>">
            <button type="submit" class="rounded-md px-3 py-1.5 text-sm font-medium text-white" style="background:#059669">Zusagen</button>
        </form>
    <?php endif; ?>
    <form method="post" action="/veranstaltungs-techniker/<?= e($respondAssignment->id) ?>/antwort" data-confirm="Für diese Veranstaltung absagen? Du siehst sie danach nicht mehr in deiner Liste.">
        <input type="hidden" name="csrf_token" value="<?= $respondCsrf ?>">
        <input type="hidden" name="answer" value="absagen">
        <input type="hidden" name="back" value="<?= e($respondBack) ?>">
        <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">Absagen</button>
    </form>
</div>
