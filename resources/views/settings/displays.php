<?php
/**
 * Settings › Stage & FOH displays (migration 168).
 *
 * @var \App\Domain\Display\DisplayClient[] $clients
 * @var \App\Domain\Display\DisplaySettings $settings
 * @var list<array{rental: \App\Domain\Rental\Rental, programs: \App\Domain\MinuteProgram\MinuteProgram[]}> $targets
 * @var array<string, ?array{program: string, room: ?string, event: ?string}> $showing
 * @var ?array{client: string, token: string} $newToken
 * @var string $origin
 */

use App\Domain\Display\DisplayClient;
use App\Domain\Display\DisplayOptions;
use App\Domain\Display\DisplayRole;
use App\Infrastructure\Http\Csrf;

use function App\Infrastructure\Http\e;

$csrf = e(Csrf::token());
$input = 'rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900';
$btn = 'inline-flex items-center rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800';
$primary = 'inline-flex items-center rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700';
$card = 'rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900';
$label = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$check = 'flex items-center gap-1.5 text-sm text-slate-700 dark:text-slate-300';
$now = new DateTimeImmutable();
$showing ??= [];
$apiBase = $origin . '/api/display/v1';
$clientUrl = $origin . '/display';

/** Assignment select: automatic, an event (its current program) or a pinned minute program. */
$targetSelect = static function (?DisplayClient $client) use ($targets, $input): string {
    $selected = match (true) {
        $client === null => 'auto',
        $client->minuteProgramId !== null => 'program:' . $client->minuteProgramId,
        $client->rentalId !== null => 'rental:' . $client->rentalId,
        default => 'auto',
    };
    $opt = static fn (string $value, string $text): string => '<option value="' . e($value) . '"' . ($value === $selected ? ' selected' : '') . '>' . e($text) . '</option>';
    $html = '<select name="target" class="mt-1 w-full ' . $input . '">';
    $html .= $opt('auto', 'Automatisch – laufendes Programm aller heutigen Veranstaltungen');
    foreach ($targets as $target) {
        $rental = $target['rental'];
        $dates = $rental->startDate->format('d.m.') . ($rental->endDate->format('Y-m-d') !== $rental->startDate->format('Y-m-d') ? '–' . $rental->endDate->format('d.m.Y') : $rental->startDate->format('Y'));
        $html .= '<optgroup label="' . e($rental->eventName . ' (' . $dates . ')') . '">';
        $html .= $opt('rental:' . $rental->id, $rental->eventName . ' – jeweils laufendes Programm');
        foreach ($target['programs'] as $program) {
            $html .= $opt('program:' . $program->id, '↳ ' . $program->name . ($program->room !== null && $program->room !== '' ? ' · ' . $program->room : '') . ' · ' . $program->startAt->format('d.m. H:i'));
        }
        $html .= '</optgroup>';
    }

    return $html . '</select>';
};

$groups = [DisplayRole::Stage->value => [], DisplayRole::Foh->value => []];
foreach ($clients as $client) {
    $groups[$client->role->value][] = $client;
}
$groupHints = [
    DisplayRole::Stage->value => 'Bühnenmonitore und Konfidenzmonitore: großer Countdown, aktueller und nächster Programmpunkt, Hinweise für die Bühne.',
    DisplayRole::Foh->value => 'Bildschirme an der Regie: kompletter Ablauf mit allen Gewerke-Hinweisen; optional mit Steuerung (Verzug, Start, Ende).',
];
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Stage- &amp; FOH-Displays</h1>
    <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-slate-400">
        Anzeigegeräte für Bühne und Regie. Sie zeigen den Live-Stand eines Minutenprogramms (Countdown, Verzug, Programmpunkte und Hinweise). Ein Gerät kann jederzeit zwischen Stagedisplay und FOH-Display umgestellt werden – der Client bleibt derselbe.
    </p>
</div>

<div class="space-y-6">
    <section id="geraete" class="<?= $card ?>">
        <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Displays</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">
            Jedes Display meldet sich mit seinem Token an der Schnittstelle <code class="font-mono"><?= e($apiBase) ?></code> an (Beschreibung: <code class="font-mono">docs/display-api.md</code>).
            Ohne eigene Software genügt ein Browser im Vollbild: <a href="/display" target="_blank" rel="noopener" class="font-mono text-brand-600 hover:underline dark:text-brand-400"><?= e($clientUrl) ?></a>.
        </p>

        <?php if ($newToken !== null): ?>
            <div class="mb-4 rounded-md border border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                <p class="font-semibold">Display-Token – nur jetzt sichtbar, bitte im Display-Client eintragen:</p>
                <p class="mt-2 break-all font-mono" id="display-token"><?= e($newToken['token']) ?></p>
                <p class="mt-2 text-xs">Browser-Display direkt einrichten (der Token steht nur im Adress-Anker und wird nicht an den Server übertragen):</p>
                <p class="mt-1 break-all font-mono text-xs" id="display-link"><?= e($clientUrl . '#token=' . $newToken['token']) ?></p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="<?= $btn ?>" data-copy-target="display-token">Token kopieren</button>
                    <button type="button" class="<?= $btn ?>" data-copy-target="display-link">Link kopieren</button>
                    <a href="<?= e('/display#token=' . $newToken['token']) ?>" target="_blank" rel="noopener" class="<?= $btn ?>">Im Browser öffnen</a>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($groups as $role => $roleClients): ?>
            <?php $roleEnum = DisplayRole::from($role); ?>
            <div class="mb-5">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e($roleEnum === DisplayRole::Stage ? 'Stagedisplays' : 'FOH-Displays') ?> <span class="font-normal text-slate-400">(<?= count($roleClients) ?>)</span></h3>
                <p class="mb-3 text-xs text-slate-500 dark:text-slate-400"><?= e($groupHints[$role]) ?></p>
                <?php if ($roleClients === []): ?>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Noch kein <?= e($roleEnum->label()) ?> angelegt.</p>
                <?php endif; ?>
                <div class="space-y-3">
                    <?php foreach ($roleClients as $client): ?>
                        <?php $online = $client->isOnline($now); $o = $client->options; $shows = $showing[$client->id] ?? null; ?>
                        <div id="display-<?= e($client->id) ?>" class="rounded-md border border-slate-200 p-4 dark:border-slate-700">
                            <form method="post" action="/einstellungen/displays/<?= e($client->id) ?>" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-block rounded-full" style="width:.625rem;height:.625rem;background: <?= $online ? '#10b981' : '#94a3b8' ?>" title="<?= $online ? 'online' : 'offline' ?>"></span>
                                    <input type="text" name="name" value="<?= e($client->name) ?>" required maxlength="120" class="<?= $input ?>" style="min-width: 14rem" aria-label="Name">
                                    <select name="role" class="<?= $input ?>" aria-label="Rolle" data-role-select>
                                        <?php foreach (DisplayRole::cases() as $r): ?>
                                            <option value="<?= e($r->value) ?>" <?= $r === $client->role ? 'selected' : '' ?>><?= e($r->label()) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label class="<?= $check ?>"><input type="checkbox" name="enabled" value="1" <?= $client->enabled ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300"> aktiv</label>
                                </div>

                                <div class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));">
                                    <label class="<?= $label ?>" style="grid-column: span 2 / span 2; min-width: 0;">Zeigt<?= $targetSelect($client) ?></label>
                                    <label class="<?= $label ?>">Nur Raum <span class="font-normal text-slate-400">(optional, bei automatischer Wahl)</span>
                                        <input type="text" name="room" value="<?= e($client->room ?? '') ?>" maxlength="120" placeholder="z. B. Großer Saal" class="mt-1 w-full <?= $input ?>">
                                    </label>
                                </div>

                                <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));">
                                    <fieldset>
                                        <legend class="<?= $label ?> mb-1">Hinweise der Gewerke</legend>
                                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                                            <?php foreach (DisplayOptions::NOTE_TRADES as $trade => $tradeLabel): ?>
                                                <label class="<?= $check ?>"><input type="checkbox" name="notes[]" value="<?= e($trade) ?>" <?= $o->showsNotes($trade) ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300"> <?= e($tradeLabel) ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                    <fieldset>
                                        <legend class="<?= $label ?> mb-1">Anzeigen</legend>
                                        <div class="flex flex-wrap gap-x-4 gap-y-1">
                                            <?php foreach (['show_clock' => ['Uhrzeit', $o->showClock], 'show_delay' => ['Verzug', $o->showDelay], 'show_next' => ['Nächster Punkt', $o->showNext], 'show_item_list' => ['Ablaufliste', $o->showItemList], 'show_description' => ['Beschreibung', $o->showDescription], 'show_mics' => ['Mikrofone', $o->showMics]] as $field => [$text, $on]): ?>
                                                <label class="<?= $check ?>"><input type="checkbox" name="<?= e($field) ?>" value="1" <?= $on ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300"> <?= e($text) ?></label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                    <div class="flex flex-wrap items-end gap-3">
                                        <label class="<?= $label ?>">Darstellung
                                            <select name="theme" class="mt-1 <?= $input ?>">
                                                <?php foreach (DisplayOptions::THEMES as $theme => $themeLabel): ?>
                                                    <option value="<?= e($theme) ?>" <?= $theme === $o->theme ? 'selected' : '' ?>><?= e($themeLabel) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label class="<?= $label ?>">Schriftgröße (%)
                                            <input type="number" name="font_scale" value="<?= (int) $o->fontScale ?>" min="50" max="200" step="5" class="mt-1 w-24 <?= $input ?>">
                                        </label>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <label class="<?= $check ?>" data-foh-only <?= $client->role === DisplayRole::Foh ? '' : 'hidden' ?>>
                                        <input type="checkbox" name="allow_control" value="1" <?= $o->allowControl ? 'checked' : '' ?> class="h-4 w-4 rounded border-slate-300">
                                        Steuern erlauben <span class="text-xs text-slate-400">(Verzug, Programmpunkt starten, Beenden, Zurücksetzen)</span>
                                    </label>
                                    <button type="submit" class="<?= $primary ?>">Speichern</button>
                                </div>
                            </form>
                            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                                Token <span class="font-mono"><?= e($client->tokenHint) ?></span>
                                · <?= $client->lastSeenAt !== null ? ($online ? 'online' : 'zuletzt gesehen ' . e($client->lastSeenAt->format('d.m.Y H:i'))) : 'noch nie verbunden' ?>
                                <?= $client->lastIp !== null ? ' · IP ' . e($client->lastIp) : '' ?>
                                <?= $client->clientVersion !== null ? ' · Version ' . e($client->clientVersion) : '' ?>
                                · zeigt gerade: <?= $shows !== null ? e(($shows['event'] !== null ? $shows['event'] . ' › ' : '') . $shows['program'] . ($shows['room'] !== null && $shows['room'] !== '' ? ' (' . $shows['room'] . ')' : '')) : '<em>kein Minutenprogramm</em>' ?>
                            </p>
                            <div class="mt-2 flex flex-wrap gap-3">
                                <form method="post" action="/einstellungen/displays/<?= e($client->id) ?>/token" data-confirm-key="settings.displays.token" data-confirm="Neuen Token für „<?= e($client->name) ?>“ erzeugen? Das Display verliert sofort die Verbindung, bis der neue Token eingetragen ist.">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <button type="submit" class="text-xs font-medium text-brand-600 hover:underline dark:text-brand-400">Neuen Token erzeugen</button>
                                </form>
                                <form method="post" action="/einstellungen/displays/<?= e($client->id) ?>/loeschen" data-confirm-key="settings.displays.delete" data-confirm="Display „<?= e($client->name) ?>“ löschen?" data-confirm-style="danger">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <form method="post" action="/einstellungen/displays" class="grid gap-3 border-t border-slate-100 pt-4 dark:border-slate-800" style="grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); align-items: end;">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <label class="<?= $label ?>">Name<input type="text" name="name" required maxlength="120" placeholder="z. B. Bühnenmonitor links" class="mt-1 w-full <?= $input ?>"></label>
            <label class="<?= $label ?>">Rolle
                <select name="role" class="mt-1 w-full <?= $input ?>">
                    <?php foreach (DisplayRole::cases() as $r): ?>
                        <option value="<?= e($r->value) ?>"><?= e($r->label()) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="<?= $label ?>" style="grid-column: span 2 / span 2; min-width: 0;">Zeigt<?= $targetSelect(null) ?></label>
            <label class="<?= $label ?>">Nur Raum <span class="font-normal text-slate-400">(optional)</span><input type="text" name="room" maxlength="120" class="mt-1 w-full <?= $input ?>"></label>
            <div><button type="submit" class="<?= $primary ?>">Display anlegen</button></div>
        </form>
        <p class="mt-2 text-xs text-slate-400 dark:text-slate-500">Neue Displays starten mit passenden Vorgaben für ihre Rolle; Anzeigeoptionen kannst du danach je Display anpassen.</p>
    </section>

    <section id="einstellungen" class="<?= $card ?>">
        <h2 class="mb-1 text-sm font-semibold text-slate-800 dark:text-slate-200">Countdown &amp; Aktualisierung</h2>
        <p class="mb-4 text-xs text-slate-500 dark:text-slate-400">Gilt für alle Displays. Der Countdown des aktuellen Programmpunkts wird gelb, wenn die Warnschwelle unterschritten ist, und rot ab der kritischen Schwelle bzw. beim Überziehen.</p>
        <form method="post" action="/einstellungen/displays/einstellungen" class="grid gap-3" style="grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <label class="<?= $label ?>">Abfrageintervall (Sek.)<input type="number" name="poll_interval_seconds" value="<?= (int) $settings->pollIntervalSeconds ?>" min="1" max="60" class="mt-1 w-full <?= $input ?>"></label>
            <label class="<?= $label ?>">Warnschwelle (Sek. Restzeit)<input type="number" name="warn_seconds" value="<?= (int) $settings->warnSeconds ?>" min="0" max="3600" class="mt-1 w-full <?= $input ?>"></label>
            <label class="<?= $label ?>">Kritisch (Sek. Restzeit)<input type="number" name="critical_seconds" value="<?= (int) $settings->criticalSeconds ?>" min="0" max="3600" class="mt-1 w-full <?= $input ?>"></label>
            <div style="align-self:end"><button type="submit" class="<?= $btn ?>">Speichern</button></div>
        </form>
    </section>
</div>
<script>
    document.querySelectorAll('[data-copy-target]').forEach(function (b) {
        b.addEventListener('click', function () {
            var el = document.getElementById(b.dataset.copyTarget);
            if (!el || !navigator.clipboard) { return; }
            navigator.clipboard.writeText(el.innerText.trim()).then(function () { b.textContent = 'Kopiert ✓'; });
        });
    });
    document.querySelectorAll('[data-role-select]').forEach(function (select) {
        var toggle = select.form.querySelector('[data-foh-only]');
        if (!toggle) { return; }
        select.addEventListener('change', function () { toggle.hidden = select.value !== 'foh'; });
    });
</script>
