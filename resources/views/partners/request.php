<?php
/**
 * @var \App\Application\Federation\Query\PartnerRequestDetail $detail
 * @var array<string, string> $errors
 */

use App\Domain\Federation\Discount;
use App\Domain\Federation\PartnerRequestKind;
use App\Domain\Federation\PartnerRequestStatus;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
$r = $detail->request;
$partner = $detail->partner;
$incoming = $r->isIncoming();
$pending = $r->status === PartnerRequestStatus::Pending;
$money = static fn (?float $v): string => $v === null ? '–' : number_format($v, 2, ',', '.') . ' €';
$card = 'rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900';
$input = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900';
$labelCls = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$btn = 'rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700';
$btnSecondary = 'rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
$errorText = $errors !== [] ? implode(' ', $errors) : null;
?>
<div class="mb-6">
    <a href="/partner" class="text-xs text-brand-700 hover:underline dark:text-brand-400">← Partner</a>
    <div class="mt-1 flex flex-wrap items-center gap-2">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($r->eventName) ?></h1>
        <span class="rounded px-1.5 py-0.5 text-xs" style="<?= e($r->status->badgeStyle()) ?>"><?= e($r->status->label()) ?></span>
    </div>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e($r->kind->label()) ?>-Anfrage <?= $incoming ? 'von' : 'an' ?> <strong><?= e($partner->name) ?></strong> ·
        <?= e($r->startDate->format('d.m.Y')) ?><?= $r->endDate != $r->startDate ? ' – ' . e($r->endDate->format('d.m.Y')) : '' ?>
        (<?= $r->deploymentDays() ?> Tag<?= $r->deploymentDays() === 1 ? '' : 'e' ?>)
        <?= $r->eventLocation !== null ? ' · ' . e($r->eventLocation) : '' ?>
    </p>
</div>

<?php if ($errorText !== null): ?>
    <p class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800"><?= e($errorText) ?></p>
<?php endif; ?>
<?php if ($r->syncError !== null): ?>
    <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800"><?= e($r->syncError) ?></p>
<?php endif; ?>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="<?= $card ?>">
            <?php if ($r->kind === PartnerRequestKind::Material): ?>
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Angefragtes Material</h2>
                <table class="min-w-full divide-y divide-slate-100 text-sm dark:divide-slate-800" data-responsive>
                    <thead class="text-left text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        <tr><th class="py-1.5">Artikel</th><th class="py-1.5 text-right">Menge</th><th class="py-1.5 text-right">Preis/Tag</th><?php if ($r->discountPercent !== null && $r->discountPercent > 0): ?><th class="py-1.5 text-right">abzgl. <?= e(Discount::format($r->discountPercent)) ?></th><?php endif; ?></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($r->items as $item): ?>
                            <?php $short = $detail->shortages[$item->articleId] ?? null; ?>
                            <tr<?php if ($incoming): ?> class="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800" data-row-link="/artikel/<?= e($item->articleId) ?>"<?php endif; ?>>
                                <td class="py-1.5 text-slate-800 dark:text-slate-200">
                                    <?php if ($incoming): ?><a class="hover:underline" href="/artikel/<?= e($item->articleId) ?>"><?= e($item->name !== '' ? $item->name : $item->articleId) ?></a><?php else: ?><?= e($item->name !== '' ? $item->name : 'Artikel') ?><?php endif; ?>
                                    <?php if ($short !== null): ?><span class="ml-1 text-xs text-red-600 dark:text-red-400">nur <?= $short ?> verfügbar</span><?php endif; ?>
                                </td>
                                <td class="py-1.5 text-right"><?= $item->quantity ?></td>
                                <td class="py-1.5 text-right"><?= e($money($item->dailyPrice)) ?></td>
                                <?php if ($r->discountPercent !== null && $r->discountPercent > 0): ?>
                                    <td class="py-1.5 text-right"><?= e($money($item->dailyPrice !== null ? Discount::apply($item->dailyPrice, $r->discountPercent) : null)) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Preise je Einsatztag ohne Staffel; abgerechnet wird nach den Preisregeln des vermietenden Partners.</p>
            <?php else: ?>
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Angefragte Techniker</h2>
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div><dt class="text-xs text-slate-400">Anzahl</dt><dd><?= $r->technicianCount ?></dd></div>
                    <div><dt class="text-xs text-slate-400">Gewerke</dt><dd><?= $r->trades !== [] ? e(implode(', ', $r->trades)) : '–' ?></dd></div>
                    <div><dt class="text-xs text-slate-400">Wunsch-Techniker</dt><dd><?= $r->preferredTechnician !== null ? e($r->preferredTechnician['name']) : 'Egal' ?></dd></div>
                </dl>
                <?php if ($r->technicians !== []): ?>
                    <h3 class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Eingeplant</h3>
                    <ul class="text-sm text-slate-700 dark:text-slate-300">
                        <?php foreach ($r->technicians as $technician): ?>
                            <li><?= e($technician['name']) ?> <span class="text-xs text-slate-500">(<?= e($technician['status']) ?>)</span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if ($incoming && $pending): ?>
            <section class="<?= $card ?>">
                <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Antworten</h2>
                <form method="post" action="/partner/anfragen/<?= e($r->id) ?>/zusagen" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <?php if ($r->kind === PartnerRequestKind::Technician): ?>
                        <fieldset>
                            <legend class="<?= $labelCls ?>">Techniker einplanen (werden wie gewohnt angefragt bzw. verplant und benachrichtigt)</legend>
                            <?php if ($detail->technicians === []): ?>
                                <p class="text-sm text-slate-500">Es gibt keine aktiven Benutzer mit dem Recht „Techniker“.</p>
                            <?php endif; ?>
                            <div class="mt-1 flex flex-wrap gap-3">
                                <?php foreach ($detail->technicians as $technician): ?>
                                    <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                                        <input type="checkbox" name="technicians[]" value="<?= e($technician['id']) ?>" <?= ($r->preferredTechnician['id'] ?? null) === $technician['id'] ? 'checked' : '' ?>> <?= e($technician['name']) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php if ($detail->trades !== []): ?>
                            <fieldset>
                                <legend class="<?= $labelCls ?>">Gewerke</legend>
                                <div class="mt-1 flex flex-wrap gap-3">
                                    <?php foreach ($detail->trades as $trade): ?>
                                        <label class="flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300">
                                            <input type="checkbox" name="trades[]" value="<?= e($trade['id']) ?>" <?= in_array($trade['name'], $r->trades, true) ? 'checked' : '' ?>> <?= e($trade['name']) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        <?php endif; ?>
                    <?php endif; ?>
                    <label class="<?= $labelCls ?>">Nachricht an <?= e($partner->name) ?> (optional)
                        <textarea name="message" rows="2" maxlength="1000" class="<?= $input ?>"></textarea>
                    </label>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Beim Zusagen wird eine Veranstaltung für <?= e($partner->name) ?> angelegt<?= $r->kind === PartnerRequestKind::Material ? ' und das Material eingeplant' : '' ?>.</p>
                        <button type="submit" class="<?= $btn ?>" <?= $detail->shortages !== [] ? 'disabled title="Nicht alles verfügbar"' : '' ?>>Zusagen</button>
                    </div>
                </form>
                <form method="post" action="/partner/anfragen/<?= e($r->id) ?>/ablehnen" class="mt-4 space-y-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <label class="<?= $labelCls ?>">Begründung (optional)
                        <input type="text" name="message" maxlength="1000" class="<?= $input ?>">
                    </label>
                    <div class="text-right"><button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-950/40">Ablehnen</button></div>
                </form>
            </section>
        <?php endif; ?>
    </div>

    <aside class="space-y-4">
        <section class="<?= $card ?> text-sm">
            <dl class="space-y-2">
                <?php if ($r->contactName !== null || $r->contactEmail !== null): ?>
                    <div><dt class="text-xs text-slate-400">Ansprechpartner</dt><dd><?= $r->contactName !== $r->contactEmail ? e($r->contactName ?? '') : '' ?><?php if ($r->contactEmail !== null): ?><?= $r->contactName !== null && $r->contactName !== $r->contactEmail ? '<br>' : '' ?><a class="text-brand-700 hover:underline dark:text-brand-400" href="mailto:<?= e($r->contactEmail) ?>"><?= e($r->contactEmail) ?></a><?php endif; ?></dd></div>
                <?php endif; ?>
                <?php if ($r->note !== null): ?>
                    <div><dt class="text-xs text-slate-400">Nachricht</dt><dd class="whitespace-pre-line"><?= e($r->note) ?></dd></div>
                <?php endif; ?>
                <?php if ($r->responseMessage !== null): ?>
                    <div><dt class="text-xs text-slate-400">Antwort</dt><dd class="whitespace-pre-line"><?= e($r->responseMessage) ?></dd></div>
                <?php endif; ?>
                <?php if ($r->discountPercent !== null): ?>
                    <div><dt class="text-xs text-slate-400">Vereinbarter Rabatt</dt><dd><?= e(Discount::format($r->discountPercent)) ?></dd></div>
                <?php endif; ?>
                <?php if ($r->rentalId !== null): ?>
                    <div><dt class="text-xs text-slate-400"><?= $incoming ? 'Angelegte Veranstaltung' : 'Eigene Veranstaltung' ?></dt><dd><a class="text-brand-700 hover:underline dark:text-brand-400" href="/veranstaltungen/<?= e($r->rentalId) ?>">Öffnen →</a></dd></div>
                <?php endif; ?>
                <div><dt class="text-xs text-slate-400">Angefragt am</dt><dd><?= e($r->createdAt->format('d.m.Y H:i')) ?></dd></div>
                <?php if ($r->respondedAt !== null): ?>
                    <div><dt class="text-xs text-slate-400">Beantwortet am</dt><dd><?= e($r->respondedAt->format('d.m.Y H:i')) ?></dd></div>
                <?php endif; ?>
            </dl>
        </section>

        <div class="flex flex-wrap gap-2">
            <?php if (!$incoming): ?>
                <form method="post" action="/partner/anfragen/<?= e($r->id) ?>/aktualisieren"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><button type="submit" class="<?= $btnSecondary ?>">Stand abfragen</button></form>
                <?php if (in_array($r->status, [PartnerRequestStatus::Pending, PartnerRequestStatus::Accepted], true)): ?>
                    <form method="post" action="/partner/anfragen/<?= e($r->id) ?>/stornieren" data-confirm-key="partners.request.cancel" data-confirm="Anfrage bei <?= e($partner->name) ?> stornieren?" data-confirm-style="danger"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-400">Stornieren</button></form>
                <?php endif; ?>
            <?php elseif (!$pending && $r->syncError !== null && $r->status !== PartnerRequestStatus::Cancelled): ?>
                <form method="post" action="/partner/anfragen/<?= e($r->id) ?>/status-senden"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><button type="submit" class="<?= $btnSecondary ?>">Antwort erneut an Partner senden</button></form>
            <?php endif; ?>
        </div>
    </aside>
</div>
