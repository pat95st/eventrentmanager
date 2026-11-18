<?php
/**
 * Settings of the route planning for tours (migration 178).
 *
 * @var \App\Domain\Routing\RoutingSettings $settings
 * @var array<string, string> $errors
 */

use function App\Infrastructure\Http\e;

$input = 'mt-1 w-full max-w-sm rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-900';
?>
<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Routenplanung</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Straßenrouten und Fahrzeiten zwischen den Stops einer Tour.</p>
</div>

<?php if ($errors !== []): ?>
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"><?= e(implode(' ', $errors)) ?></div>
<?php endif; ?>

<form method="post" action="/einstellungen/routenplanung" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div>
        <label for="api_key" class="block text-xs font-medium text-slate-600 dark:text-slate-400">API-Schlüssel von OpenRouteService</label>
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
            Kostenlos nach Registrierung auf openrouteservice.org (Heidelberg Institute for Geoinformation Technology).
            Ohne Schlüssel zeigt die Karte die Stops nur als Luftlinie, ohne Fahrzeiten.
        </p>
        <input type="password" id="api_key" name="api_key" autocomplete="off" placeholder="<?= $settings->hasApiKey() ? 'gespeichert – leer lassen, um ihn zu behalten' : '' ?>" class="<?= $input ?>">
        <?php if ($settings->hasApiKey()): ?>
            <label class="mt-2 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_key" value="1"> Gespeicherten Schlüssel entfernen</label>
        <?php endif; ?>
    </div>
    <div>
        <label for="buffer_minutes" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Puffer zwischen Abbau + Fahrt und dem nächsten Aufbau (Minuten)</label>
        <input type="number" id="buffer_minutes" name="buffer_minutes" min="0" max="600" value="<?= (int) $settings->bufferMinutes ?>" class="<?= $input ?> max-w-[8rem]">
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Reicht die Zeit zwischen zwei Stops nicht für Fahrt und Puffer, erscheint eine Warnung.</p>
    </div>
    <div class="flex border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
    </div>
</form>
