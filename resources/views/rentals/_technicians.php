<?php
/**
 * Techniker & Gewerke einer Veranstaltung (Migration 134), für Disponenten.
 *
 * @var \App\Domain\Rental\Rental $rental
 * @var \App\Application\Technician\Query\RentalTechnicianTeam|null $technicianTeam
 */

use App\Domain\Technician\RentalTechnicianStatus;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$technicianTeam ??= null;
if ($technicianTeam === null) {
    return;
}
$csrf = e(Csrf::token());
$btn = 'rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
$tradeChecks = function (string $name, array $selected, array $limitTo = []) use ($technicianTeam): void {
    $trades = $technicianTeam->allTrades;
    if ($limitTo !== []) {
        // benötigte Gewerke zuerst, übrige danach
        usort($trades, static fn ($a, $b) => (int) !in_array($a->id, $limitTo, true) <=> (int) !in_array($b->id, $limitTo, true));
    }
    foreach ($trades as $trade): ?>
        <label class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 px-2.5 py-1 text-xs text-slate-700 dark:border-slate-700 dark:text-slate-300" <?= $limitTo !== [] && !in_array($trade->id, $limitTo, true) ? 'style="opacity:.7"' : '' ?>>
            <input type="checkbox" name="<?= e($name) ?>[]" value="<?= e($trade->id) ?>" <?= in_array($trade->id, $selected, true) ? 'checked' : '' ?>>
            <?= e($trade->name) ?>
        </label>
    <?php endforeach;
};
$uncovered = $technicianTeam->uncoveredTradeNames();
?>
<div id="techniker" class="mb-6 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Techniker &amp; Gewerke</h2>
        <a href="/einstellungen/techniker" class="text-xs text-slate-500 hover:underline dark:text-slate-400">Gewerke verwalten</a>
    </div>

    <?php if ($technicianTeam->allTrades === []): ?>
        <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">Es sind noch keine Gewerke angelegt (Einstellungen › Techniker &amp; Gewerke).</p>
    <?php else: ?>
        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/gewerke" class="mb-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <p class="mb-1.5 text-xs font-medium text-slate-600 dark:text-slate-400">Benötigte Gewerke</p>
            <div class="flex flex-wrap items-center gap-2">
                <?php $tradeChecks('trade_ids', $technicianTeam->rentalTradeIds); ?>
                <button type="submit" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Speichern</button>
            </div>
            <?php if ($uncovered !== []): ?>
                <p class="mt-2 text-xs" style="color:#b45309">Noch ohne Techniker: <?= e(implode(', ', $uncovered)) ?></p>
            <?php endif; ?>
        </form>
    <?php endif; ?>

    <?php if ($technicianTeam->rows !== []): ?>
        <div class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($technicianTeam->rows as $row): $a = $row['assignment']; $u = $row['user']; ?>
                <div class="py-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($u?->displayName() ?? 'Gelöschter Benutzer') ?></span>
                            <span class="ml-1 rounded-full px-2 py-0.5 text-xs font-medium" style="<?= $a->status->badgeStyle() ?>"><?= e($a->status->label()) ?></span>
                            <span class="block text-xs text-slate-400 dark:text-slate-500">
                                <?= $a->status === RentalTechnicianStatus::Requested ? 'angefragt am ' . e($a->requestedAt->format('d.m.Y H:i')) . ($a->reminderCount > 0 ? ' · ' . $a->reminderCount . '× erinnert' : '') : '' ?>
                                <?= $a->respondedAt !== null ? 'Antwort am ' . e($a->respondedAt->format('d.m.Y H:i')) : '' ?>
                                <?= $a->status === RentalTechnicianStatus::Assigned ? 'direkt verplant' : '' ?>
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
                            <?php if ($a->status !== RentalTechnicianStatus::Declined): ?>
                                <form method="post" action="/veranstaltungs-techniker/<?= e($a->id) ?>/benachrichtigen">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <button type="submit" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= $a->status === RentalTechnicianStatus::Requested ? 'Erneut erinnern' : 'Einladung erneut senden' ?></button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/techniker">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="user_id" value="<?= e($a->userId) ?>">
                                    <?php foreach ($a->tradeIds as $tid): ?><input type="hidden" name="trade_ids[]" value="<?= e($tid) ?>"><?php endforeach; ?>
                                    <button type="submit" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Erneut anfragen</button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="/veranstaltungs-techniker/<?= e($a->id) ?>/loeschen" data-confirm="<?= e(($u?->displayName() ?? 'Techniker') . ' von der Veranstaltung entfernen?') ?>" data-confirm-style="danger">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
                            </form>
                        </div>
                    </div>
                    <?php if ($technicianTeam->allTrades !== []): ?>
                        <form method="post" action="/veranstaltungs-techniker/<?= e($a->id) ?>" class="mt-2 flex flex-wrap items-center gap-2">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <?php $tradeChecks('trade_ids', $a->tradeIds, $technicianTeam->rentalTradeIds); ?>
                            <input type="text" name="note" value="<?= e($a->note ?? '') ?>" maxlength="500" placeholder="Hinweis" class="rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700" style="min-width:10rem">
                            <button type="submit" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Speichern</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($technicianTeam->availableTechnicians !== []): ?>
        <form method="post" action="/veranstaltungen/<?= e($rental->id) ?>/techniker" class="space-y-2 border-t border-slate-100 pt-3 dark:border-slate-800">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label for="technician-user" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Techniker hinzufügen</label>
                    <select id="technician-user" name="user_id" required class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                        <option value="">Bitte wählen…</option>
                        <?php foreach ($technicianTeam->availableTechnicians as $tech): ?>
                            <option value="<?= e($tech->id) ?>"><?= e($tech->displayName()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="text" name="note" maxlength="500" placeholder="Hinweis (optional)" class="rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700" style="min-width:12rem">
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Anfragen / einplanen</button>
            </div>
            <?php if ($technicianTeam->allTrades !== []): ?>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-xs text-slate-500 dark:text-slate-400">Gewerke:</span>
                    <?php $tradeChecks('trade_ids', [], $technicianTeam->rentalTradeIds); ?>
                </div>
            <?php endif; ?>
            <p class="text-xs text-slate-400 dark:text-slate-500">Techniker, die zustimmen müssen, werden angefragt; die übrigen direkt eingeplant. Beide erhalten eine E-Mail mit Termineinladung und – falls hinterlegt – eine Messenger-Nachricht.</p>
        </form>
    <?php elseif ($technicianTeam->rows === []): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400">Es gibt noch keine Benutzer mit dem Recht „Techniker“ (Benutzerverwaltung).</p>
    <?php endif; ?>
</div>
