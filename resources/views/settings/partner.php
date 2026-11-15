<?php
/**
 * @var \App\Application\Federation\Query\PartnerDetail $detail
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

use App\Domain\Federation\Discount;
use App\Domain\Federation\DiscountProposalSource;
use App\Domain\Federation\PartnerStatus;
use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\View;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\renderLiveSearchField;

$csrf = e(Csrf::token());
$partner = $detail->partner;
$card = 'rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900';
$btn = 'rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700';
$btnSecondary = 'rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
$generalError = $errors['partner'] ?? $errors['discount'] ?? null;
?>
<div class="mb-6">
    <a href="/einstellungen/partner" class="text-xs text-brand-700 hover:underline dark:text-brand-400">← Partner-Instanzen</a>
    <div class="mt-1 flex flex-wrap items-center gap-2">
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($partner->name) ?></h1>
        <span class="rounded px-1.5 py-0.5 text-xs" style="<?= e($partner->status->badgeStyle()) ?>"><?= e($partner->status->label()) ?></span>
    </div>
    <p class="font-mono text-sm text-slate-500 dark:text-slate-400"><?= e($partner->baseUrl) ?></p>
    <?php if ($partner->profile->addressLine() !== ''): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e($partner->profile->addressLine()) ?><?= $partner->profile->email !== null ? ' · ' . e($partner->profile->email) : '' ?></p>
    <?php endif; ?>
    <p class="text-xs text-slate-400 dark:text-slate-500">
        Letzter Kontakt: <?= $partner->lastContactAt !== null ? e($partner->lastContactAt->format('d.m.Y H:i')) : '–' ?>
        <?php if ($partner->confirmedAt !== null): ?> · Partner seit <?= e($partner->confirmedAt->format('d.m.Y')) ?><?php endif; ?>
    </p>
</div>

<?php if ($generalError !== null): ?>
    <p class="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200 dark:bg-red-950/40 dark:text-red-300 dark:ring-red-800"><?= e($generalError) ?></p>
<?php elseif ($partner->lastError !== null): ?>
    <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">Letzter Fehler bei der Verbindung: <?= e($partner->lastError) ?></p>
<?php endif; ?>

<div class="space-y-6">
    <?php if ($partner->status === PartnerStatus::PendingConfirmation): ?>
        <section class="<?= $card ?>">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300">Partnerschaft bestätigen</h2>
            <p class="mb-3 text-sm text-slate-600 dark:text-slate-400">
                <?= e($partner->name) ?> hat deinen Einladungslink verwendet
                <?php if ($partner->hasPendingProposal()): ?>
                    und schlägt vor: wir gewähren <strong><?= e(Discount::format((float) $partner->proposedDiscountGranted)) ?></strong>,
                    <?= e($partner->name) ?> gewährt uns <strong><?= e(Discount::format((float) $partner->proposedDiscountReceived)) ?></strong>.
                    Übernimmst du die Werte, gilt der Rabatt sofort; geänderte Werte gehen als Gegenvorschlag an den Partner.
                <?php endif; ?>
            </p>
            <form method="post" action="/einstellungen/partner/<?= e($partner->id) ?>/bestaetigen">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <?= View::render('settings/_partner-agreement-fields', [
                    'errors' => $errors,
                    'old' => $old,
                    'granted' => (float) ($partner->proposedDiscountGranted ?? 0),
                    'received' => (float) ($partner->proposedDiscountReceived ?? 0),
                    'withSettings' => true,
                    'autoAccept' => $partner->autoAcceptRequests,
                    'allowTechnicians' => $partner->allowTechnicianRequests,
                    'partnerName' => $partner->name,
                ]) ?>
                <div class="mt-4 text-right"><button type="submit" class="<?= $btn ?>">Partnerschaft bestätigen</button></div>
            </form>
            <form method="post" action="/einstellungen/partner/<?= e($partner->id) ?>/ablehnen" class="mt-2 text-right" data-confirm="Partnerschaft mit „<?= e($partner->name) ?>“ ablehnen?" data-confirm-style="danger">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">Ablehnen</button>
            </form>
        </section>
    <?php elseif ($partner->status === PartnerStatus::AwaitingPartner): ?>
        <section class="<?= $card ?>">
            <p class="text-sm text-slate-600 dark:text-slate-400">
                Die Anfrage liegt bei <?= e($partner->name) ?>. Sobald dort bestätigt wird, ist die Partnerschaft aktiv.
                <?php if ($partner->hasPendingProposal()): ?>
                    Vorgeschlagener Rabatt: wir gewähren <?= e(Discount::format((float) $partner->proposedDiscountGranted)) ?>,
                    <?= e($partner->name) ?> gewährt uns <?= e(Discount::format((float) $partner->proposedDiscountReceived)) ?>.
                <?php endif; ?>
            </p>
        </section>
    <?php endif; ?>

    <?php if ($partner->isActive()): ?>
        <div class="flex flex-wrap gap-2">
            <a href="/partner/<?= e($partner->id) ?>/materialpool" class="<?= $btn ?>">Materialpool ansehen</a>
            <a href="/partner/<?= e($partner->id) ?>/techniker" class="<?= $btnSecondary ?>">Techniker anfragen</a>
        </div>

        <section class="<?= $card ?>">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Rabatt</h2>
            <dl class="mb-3 grid grid-cols-2 gap-3 text-sm sm:max-w-md">
                <div><dt class="text-xs text-slate-400 dark:text-slate-500">Wir gewähren</dt><dd class="text-lg font-semibold text-slate-800 dark:text-slate-200"><?= e(Discount::format($partner->discountGranted)) ?></dd></div>
                <div><dt class="text-xs text-slate-400 dark:text-slate-500"><?= e($partner->name) ?> gewährt uns</dt><dd class="text-lg font-semibold text-slate-800 dark:text-slate-200"><?= e(Discount::format($partner->discountReceived)) ?></dd></div>
            </dl>
            <?php if ($partner->hasPendingProposal()): ?>
                <div class="mb-3 rounded-md bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-800">
                    <?php if ($partner->proposalFrom === DiscountProposalSource::Them): ?>
                        <p class="mb-2"><?= e($partner->name) ?> schlägt vor: wir gewähren <strong><?= e(Discount::format((float) $partner->proposedDiscountGranted)) ?></strong>, <?= e($partner->name) ?> gewährt uns <strong><?= e(Discount::format((float) $partner->proposedDiscountReceived)) ?></strong>.</p>
                        <div class="flex gap-2">
                            <form method="post" action="/einstellungen/partner/<?= e($partner->id) ?>/rabatt/annehmen"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><button type="submit" class="<?= $btn ?>">Zustimmen</button></form>
                            <form method="post" action="/einstellungen/partner/<?= e($partner->id) ?>/rabatt/ablehnen"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><button type="submit" class="<?= $btnSecondary ?>">Ablehnen</button></form>
                        </div>
                    <?php else: ?>
                        <p class="mb-2">Dein Vorschlag wartet auf Zustimmung: wir gewähren <?= e(Discount::format((float) $partner->proposedDiscountGranted)) ?>, <?= e($partner->name) ?> gewährt uns <?= e(Discount::format((float) $partner->proposedDiscountReceived)) ?>.</p>
                        <form method="post" action="/einstellungen/partner/<?= e($partner->id) ?>/rabatt/ablehnen"><input type="hidden" name="csrf_token" value="<?= $csrf ?>"><button type="submit" class="<?= $btnSecondary ?>">Vorschlag zurückziehen</button></form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <details <?= isset($errors['discount']) || isset($errors['discountGranted']) || isset($errors['discountReceived']) ? 'open' : '' ?>>
                <summary class="cursor-pointer text-sm font-medium text-brand-700 dark:text-brand-400">Neuen Rabatt vorschlagen</summary>
                <form method="post" action="/einstellungen/partner/<?= e($partner->id) ?>/rabatt" class="mt-3">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <?= View::render('settings/_partner-agreement-fields', [
                        'errors' => $errors,
                        'old' => $old,
                        'granted' => $partner->discountGranted,
                        'received' => $partner->discountReceived,
                        'withSettings' => false,
                        'autoAccept' => false,
                        'allowTechnicians' => false,
                        'partnerName' => $partner->name,
                    ]) ?>
                    <div class="mt-3 text-right"><button type="submit" class="<?= $btn ?>">Vorschlag senden</button></div>
                </form>
            </details>
        </section>
    <?php endif; ?>

    <?php if (in_array($partner->status, [PartnerStatus::Active, PartnerStatus::AwaitingPartner], true)): ?>
        <section class="<?= $card ?>">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Einstellungen</h2>
            <form method="post" action="/einstellungen/partner/<?= e($partner->id) ?>/einstellungen" class="space-y-3">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="auto_accept" value="1" class="mt-0.5" <?= $partner->autoAcceptRequests ? 'checked' : '' ?>>
                    <span>Material-Anfragen automatisch eintragen und zusagen, wenn alles verfügbar ist</span>
                </label>
                <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="allow_technicians" value="1" class="mt-0.5" <?= $partner->allowTechnicianRequests ? 'checked' : '' ?>>
                    <span>Partner darf unsere Techniker sehen und anfragen</span>
                </label>
                <div class="max-w-md">
                    <label for="customer_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Kunde für Vermietungen an diesen Partner</label>
                    <?php renderLiveSearchField('customer_id', '/kunden/suche', $partner->customerId, $detail->customerLabel, 'Wird bei der ersten Zusage automatisch angelegt'); ?>
                </div>
                <div class="text-right"><button type="submit" class="<?= $btn ?>">Speichern</button></div>
            </form>
        </section>
    <?php endif; ?>

    <section class="<?= $card ?>">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Anfragen</h2>
        <?= View::render('partners/_request-list', ['requests' => array_map(static fn ($r) => new \App\Application\Federation\Query\PartnerRequestView($r, $partner->name), $detail->requests), 'showPartner' => false]) ?>
    </section>

    <section class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-200 bg-white p-4 dark:border-red-900 dark:bg-slate-900">
        <form method="post" action="/einstellungen/partner/<?= e($partner->id) ?>/testen">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <button type="submit" class="<?= $btnSecondary ?>">Verbindung testen</button>
        </form>
        <div class="flex flex-wrap gap-2">
            <?php if (in_array($partner->status, [PartnerStatus::Active, PartnerStatus::AwaitingPartner], true)): ?>
                <form method="post" action="/einstellungen/partner/<?= e($partner->id) ?>/beenden" data-confirm="Partnerschaft mit „<?= e($partner->name) ?>“ beenden? Beide Seiten verlieren den Zugriff auf Materialpool und Techniker." data-confirm-style="danger">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">Partnerschaft beenden</button>
                </form>
            <?php endif; ?>
            <?php if (!$partner->isActive()): ?>
                <form method="post" action="/einstellungen/partner/<?= e($partner->id) ?>/loeschen" data-confirm="„<?= e($partner->name) ?>“ samt allen Anfragen löschen? Bereits angelegte Veranstaltungen bleiben erhalten." data-confirm-style="danger">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">Löschen</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>
