<?php
/**
 * @var \App\Domain\Technician\Trade[] $trades
 * @var \App\Domain\Technician\TechnicianSettings $settings
 */

use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
$input = 'rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700';
$btn = 'rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Techniker &amp; Gewerke</h1>
    <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
        Gewerke werden an Veranstaltungen als benötigt markiert und den Technikern zugeordnet (ein Techniker kann mehrere Gewerke bedienen).
        Ob ein Techniker direkt verplant werden kann oder erst zustimmen muss, stellst du in der <a href="/benutzer" class="underline">Benutzerverwaltung</a> beim jeweiligen Benutzer ein.
        Die Texte der Anfragen findest du unter <a href="/einstellungen/email-vorlagen" class="underline">E-Mail-Vorlagen</a> und <a href="/einstellungen/messenger-vorlagen" class="underline">Nachrichtendienste-Vorlagen</a>.
    </p>
</div>

<div class="space-y-6">
    <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-200">Gewerke</h2>
        <?php if ($trades === []): ?>
            <p class="mb-3 text-sm text-slate-500 dark:text-slate-400">Noch keine Gewerke angelegt – z. B. Ton, Licht, Video, Rigging, Strom, Bühne.</p>
        <?php endif; ?>
        <div class="space-y-2">
            <?php foreach ($trades as $trade): ?>
                <div class="flex flex-wrap items-center gap-2">
                    <form method="post" action="/einstellungen/techniker/gewerke/<?= e($trade->id) ?>" class="flex flex-wrap items-center gap-2">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <input type="text" name="name" value="<?= e($trade->name) ?>" required maxlength="100" aria-label="Name" class="<?= $input ?>" style="min-width:14rem">
                        <input type="number" name="sort_order" value="<?= e((string) $trade->sortOrder) ?>" aria-label="Reihenfolge" title="Reihenfolge" class="<?= $input ?>" style="width:5rem">
                        <button type="submit" class="<?= $btn ?>">Speichern</button>
                    </form>
                    <form method="post" action="/einstellungen/techniker/gewerke/<?= e($trade->id) ?>/loeschen" data-confirm="Gewerk „<?= e($trade->name) ?>“ löschen? Es wird auch bei allen Veranstaltungen und Technikern entfernt." data-confirm-style="danger">
                        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
        <form method="post" action="/einstellungen/techniker/gewerke" class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="text" name="name" required maxlength="100" placeholder="Neues Gewerk" class="<?= $input ?>" style="min-width:14rem">
            <input type="number" name="sort_order" value="<?= count($trades) * 10 + 10 ?>" aria-label="Reihenfolge" class="<?= $input ?>" style="width:5rem">
            <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Hinzufügen</button>
        </form>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Erinnerungen bei offenen Anfragen</h2>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Hat ein angefragter Techniker nach so vielen Stunden noch nicht zu- oder abgesagt, wird er erneut per E-Mail (und Messenger) erinnert. Mehrere Werte durch Komma trennen; leer = keine Erinnerungen. Nur für Veranstaltungen, die noch nicht vorbei sind.</p>
        <form method="post" action="/einstellungen/techniker/erinnerungen" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <label for="reminder_hours" class="text-sm text-slate-700 dark:text-slate-300">Nach</label>
            <input type="text" id="reminder_hours" name="reminder_hours" value="<?= e($settings->reminderHoursText()) ?>" placeholder="z. B. 24, 72" class="<?= $input ?>" style="width:12rem">
            <span class="text-sm text-slate-700 dark:text-slate-300">Stunden</span>
            <button type="submit" class="<?= $btn ?>">Speichern</button>
        </form>
        <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">Der Versand läuft über den Cronjob <code>bin/send-technician-reminders.php</code> (empfohlen: stündlich).</p>
    </section>
</div>
