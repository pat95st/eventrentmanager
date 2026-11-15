<?php
/**
 * @var \App\Application\Federation\Query\PartnerOverview $overview
 * @var string|null $newLink
 * @var array<string, string> $errors
 * @var array<string, mixed> $old
 */

use App\Domain\Federation\Discount;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
$card = 'rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900';
$input = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900';
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Partner-Instanzen</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        Befreunde diese Installation mit anderen Firmen, die diese Software nutzen. Partner sehen gegenseitig ihren Materialpool,
        fragen Vermietungen direkt an und können mit wenigen Klicks Techniker anfragen. So geht's: Eine Seite erstellt einen
        Einladungslink, die andere Seite gibt ihn hier ein und legt die Bedingungen fest, danach bestätigt die einladende Seite.
    </p>
</div>

<?php if (!$overview->baseUrlConfigured): ?>
    <p class="mb-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">
        Partner erreichen diese Instanz unter <span class="font-mono"><?= e($overview->baseUrl) ?></span> (aus der aktuellen Adresse abgeleitet).
        Bitte die öffentlich erreichbare Basis-URL unter <a href="/einstellungen/integrationen" class="underline">Einstellungen › Integrationen</a> hinterlegen.
    </p>
<?php endif; ?>

<div class="space-y-6">
    <section class="<?= $card ?>">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Partner</h2>
        <?php if ($overview->partners === []): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400">Noch keine Partner-Instanzen.</p>
        <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($overview->partners as $partner): ?>
                    <a href="/einstellungen/partner/<?= e($partner->id) ?>" class="flex flex-wrap items-center justify-between gap-2 py-2 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($partner->name) ?></p>
                            <p class="font-mono text-xs text-slate-500 dark:text-slate-400"><?= e($partner->baseUrl) ?></p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                            <?php if ($partner->isActive()): ?>
                                <span>Rabatt: wir <?= e(Discount::format($partner->discountGranted)) ?> · Partner <?= e(Discount::format($partner->discountReceived)) ?></span>
                            <?php endif; ?>
                            <?php if ($partner->hasPendingProposal()): ?>
                                <span class="rounded px-1.5 py-0.5" style="background:#fef3c7;color:#92400e">Rabattvorschlag offen</span>
                            <?php endif; ?>
                            <span class="rounded px-1.5 py-0.5" style="<?= e($partner->status->badgeStyle()) ?>"><?= e($partner->status->label()) ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="<?= $card ?>">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300">1. Einladungslink erstellen</h2>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Schicke den Link an die andere Firma. Sie gibt ihn in ihrer Instanz ein; danach bestätigst du die Partnerschaft hier.</p>
            <?php if ($newLink !== null): ?>
                <div class="mb-3 rounded-md bg-emerald-50 p-3 ring-1 ring-emerald-200 dark:bg-emerald-950/40 dark:ring-emerald-800">
                    <p class="mb-1 text-xs font-medium text-emerald-800 dark:text-emerald-300">Neuer Einladungslink (wird nur jetzt angezeigt):</p>
                    <div class="flex gap-2">
                        <input type="text" readonly value="<?= e($newLink) ?>" id="partner-invite-link" class="w-full rounded-md border border-slate-300 px-2 py-1.5 font-mono text-xs dark:border-slate-700 dark:bg-slate-900" onclick="this.select()">
                        <button type="button" class="rounded-md border border-slate-300 px-2 text-xs dark:border-slate-700" onclick="navigator.clipboard && navigator.clipboard.writeText(document.getElementById('partner-invite-link').value); this.textContent='Kopiert';">Kopieren</button>
                    </div>
                </div>
            <?php endif; ?>
            <form method="post" action="/einstellungen/partner/einladungen" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <label class="min-w-0 flex-1 text-xs font-medium text-slate-600 dark:text-slate-400">Notiz (optional, z. B. für wen)
                    <input type="text" name="note" maxlength="255" class="<?= $input ?>">
                </label>
                <button type="submit" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">Link erstellen</button>
            </form>
            <?php if ($overview->invitations !== []): ?>
                <h3 class="mb-1 mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Offene Einladungen</h3>
                <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                    <?php foreach ($overview->invitations as $invitation): ?>
                        <?php $expired = $invitation->expiresAt <= new DateTimeImmutable(); ?>
                        <li class="flex items-center justify-between gap-2 py-1.5">
                            <span class="text-slate-700 dark:text-slate-300">
                                <?= e($invitation->note ?? 'Ohne Notiz') ?>
                                <span class="text-xs text-slate-400"><?= $expired ? 'abgelaufen' : 'gültig bis ' . e($invitation->expiresAt->format('d.m.Y H:i')) ?></span>
                            </span>
                            <form method="post" action="/einstellungen/partner/einladungen/<?= e($invitation->id) ?>/loeschen">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <button type="submit" class="text-xs text-red-600 hover:underline dark:text-red-400">Widerrufen</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="<?= $card ?>">
            <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300">2. Einladungslink eines Partners eingeben</h2>
            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Du hast einen Link von einer anderen Firma bekommen? Füge ihn hier ein. Im nächsten Schritt legst du Rabatt und Einstellungen fest.</p>
            <form method="post" action="/einstellungen/partner/verbinden" class="space-y-2">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="url" name="link" required placeholder="https://…/api/federation/v1/invitations/…" value="<?= e((string) ($old['link'] ?? '')) ?>" class="<?= $input ?> font-mono">
                <?php if (isset($errors['link'])): ?><p class="text-xs text-red-600 dark:text-red-400"><?= e($errors['link']) ?></p><?php endif; ?>
                <div class="text-right">
                    <button type="submit" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-medium text-white hover:bg-brand-700">Weiter</button>
                </div>
            </form>
        </section>
    </div>
</div>
