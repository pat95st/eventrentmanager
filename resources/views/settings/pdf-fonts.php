<?php
/** @var \App\Domain\Settings\PdfFont[] $fonts */
/** @var array<string, string> $assignments Bereich => Schrift-ID */
/** @var string[] $suggestions */

use App\Domain\Settings\PdfFontRole;
use App\Domain\Settings\UiFontRole;
use function App\Infrastructure\Http\e;

$csrf = e(\App\Infrastructure\Http\Csrf::token());
$styleLabels = ['regular' => 'Normal', 'bold' => 'Fett', 'italic' => 'Kursiv', 'boldItalic' => 'Fett kursiv'];
$fontsById = [];
foreach ($fonts as $font) {
    $fontsById[$font->id] = $font;
}
$input = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900';
$card = 'rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900';
?>
<style>
<?php foreach ($fonts as $font): ?>
<?php foreach (['regular' => [400, 'normal'], 'bold' => [700, 'normal'], 'italic' => [400, 'italic'], 'boldItalic' => [700, 'italic']] as $style => [$weight, $fontStyle]): ?>
<?php if ($font->files()[$style] !== null): ?>
  @font-face { font-family: 'pdf-<?= e($font->id) ?>'; src: url('/einstellungen/schriften/<?= e($font->id) ?>/datei?style=<?= $style ?>') format('truetype'); font-weight: <?= $weight ?>; font-style: <?= $fontStyle ?>; }
<?php endif; ?>
<?php endforeach; ?>
<?php endforeach; ?>
</style>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Schriftarten</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Schriften aus Google Fonts (oder hochgeladen) für die Bereiche in allen PDF-Dokumenten und -Listen sowie für die Oberfläche der Anwendung.</p>
</div>

<div class="max-w-3xl space-y-6">
    <!-- Zuordnung -->
    <form method="post" action="/einstellungen/schriften/zuordnung" class="<?= $card ?> space-y-5">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Dokumente (PDF): Schrift je Bereich</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">Angebot, AB, Rechnung, Mahnung, Packliste, Preisliste, Stückliste. Ohne Auswahl gilt die Schrift von „Text“, ohne diese die bisherige Standardschrift Helvetica.</p>
            </div>
            <a href="/einstellungen/schriften/vorschau.pdf" target="_blank" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Vorschau-PDF</a>
        </div>

        <?php foreach (PdfFontRole::cases() as $role): ?>
            <?php $selected = $assignments[$role->value] ?? ''; ?>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-center">
                <div>
                    <label for="role_<?= e($role->value) ?>" class="block text-sm font-medium text-slate-700 dark:text-slate-300"><?= e($role->label()) ?></label>
                    <p class="text-xs text-slate-400 dark:text-slate-500"><?= e($role->hint()) ?></p>
                </div>
                <div>
                    <select id="role_<?= e($role->value) ?>" name="role_<?= e($role->value) ?>" data-font-select data-preview="preview_<?= e($role->value) ?>" class="<?= $input ?>">
                        <option value=""><?= $role === PdfFontRole::Body ? 'Helvetica (Standard)' : 'wie „Text“' ?></option>
                        <?php foreach ($fonts as $font): ?>
                            <option value="<?= e($font->id) ?>" <?= $selected === $font->id ? 'selected' : '' ?>><?= e($font->family) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p id="preview_<?= e($role->value) ?>" class="mt-1 truncate text-slate-700 dark:text-slate-300" style="font-size:1.05rem">Angebot · Rechnung 1.249,00 € · ÄÖÜ äöü ß</p>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Zuordnung speichern</button>
        </div>
    </form>

    <!-- Oberfläche -->
    <form id="oberflaeche" method="post" action="/einstellungen/schriften/oberflaeche" class="<?= $card ?> space-y-5">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Oberfläche: Schrift je Bereich</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Gilt für alle Benutzer, auch auf der Anmeldeseite und in der installierten App. Ohne Auswahl gilt die Schrift von „Text“, ohne diese die Systemschrift des Geräts.
                Die Schriftdateien liefert der eigene Server aus – es wird nichts von Google geladen.
            </p>
        </div>

        <?php foreach (UiFontRole::cases() as $role): ?>
            <?php $selected = $assignments[$role->value] ?? ''; ?>
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-center">
                <div>
                    <label for="<?= e($role->value) ?>" class="block text-sm font-medium text-slate-700 dark:text-slate-300"><?= e($role->label()) ?></label>
                    <p class="text-xs text-slate-400 dark:text-slate-500"><?= e($role->hint()) ?></p>
                </div>
                <div>
                    <select id="<?= e($role->value) ?>" name="<?= e($role->value) ?>" data-ui-font-select data-role="<?= e($role->value) ?>" data-preview="preview_<?= e($role->value) ?>" class="<?= $input ?>">
                        <option value=""><?= match ($role) { UiFontRole::Body => 'Systemschrift (Standard)', UiFontRole::Numbers => 'Festbreitenschrift (Standard)', default => 'wie „Text“' } ?></option>
                        <?php foreach ($fonts as $font): ?>
                            <option value="<?= e($font->id) ?>" <?= $selected === $font->id ? 'selected' : '' ?>><?= e($font->family) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p id="preview_<?= e($role->value) ?>" class="mt-1 truncate text-slate-700 dark:text-slate-300" style="font-size:1.05rem"><?= $role === UiFontRole::Numbers ? 'THO-PLM-0012 · RE-2026-0042 · K-10001' : 'Veranstaltungen · Artikel · ÄÖÜ äöü ß 1.249,00 €' ?></p>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Schriften der Oberfläche speichern</button>
        </div>
    </form>

    <!-- Verfügbare Schriften -->
    <div class="<?= $card ?>">
        <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Verfügbare Schriften</h2>
        <?php if ($fonts === []): ?>
            <p class="text-sm text-slate-500 dark:text-slate-400">Noch keine Schrift geladen. Unten eine Schrift aus Google Fonts hinzufügen.</p>
        <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($fonts as $font): ?>
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="text-slate-900 dark:text-slate-100" style="font-family:'pdf-<?= e($font->id) ?>', sans-serif;font-size:1.25rem"><?= e($font->family) ?></p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                <?= $font->source === 'google' ? 'Google Fonts' : 'Hochgeladen' ?> ·
                                <?php foreach ($styleLabels as $style => $label): ?>
                                    <?php $has = $font->files()[$style] !== null; ?>
                                    <span style="<?= $has ? '' : 'text-decoration:line-through;opacity:.55' ?>" title="<?= $has ? 'vorhanden' : 'nicht vorhanden, wird im PDF nachgebildet' ?>"><?= e($label) ?></span><?= $style !== 'boldItalic' ? ', ' : '' ?>
                                <?php endforeach; ?>
                            </p>
                        </div>
                        <form method="post" action="/einstellungen/schriften/<?= e($font->id) ?>/loeschen" data-confirm="Die Schrift „<?= e($font->family) ?>“ entfernen? Bereiche mit dieser Schrift fallen auf den Standard zurück." data-confirm-style="danger">
                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                            <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Google Fonts -->
    <form method="post" action="/einstellungen/schriften/google" class="<?= $card ?> space-y-3">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <div>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300">Schrift aus Google Fonts hinzufügen</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                Namen wie auf <a href="https://fonts.google.com" target="_blank" rel="noopener" class="underline">fonts.google.com</a> eingeben, z. B. „Open Sans“. Der Server lädt die Schnitte Normal, Fett, Kursiv und Fett kursiv einmalig herunter.
                Die PDFs enthalten die Schrift selbst. Beim Öffnen wird nichts von Google geladen.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <input name="family" list="google-font-suggestions" required placeholder="z. B. Roboto" class="<?= $input ?>" style="max-width:20rem" autocomplete="off">
            <datalist id="google-font-suggestions">
                <?php foreach ($suggestions as $suggestion): ?>
                    <option value="<?= e($suggestion) ?>"></option>
                <?php endforeach; ?>
            </datalist>
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700" data-loading-text="Wird geladen …">Hinzufügen</button>
        </div>
    </form>

    <!-- Upload -->
    <details class="<?= $card ?>">
        <summary class="cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-300">Eigene Schrift hochladen (TTF)</summary>
        <form method="post" action="/einstellungen/schriften/hochladen" enctype="multipart/form-data" class="mt-4 space-y-3">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <p class="text-xs text-slate-500 dark:text-slate-400">Falls der Server Google Fonts nicht erreicht oder eine eigene Hausschrift verwendet werden soll. Nur TrueType-Dateien (.ttf); bitte die Lizenz der Schrift beachten.</p>
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300">Name der Schrift *</label>
                <input name="family" required class="mt-1 <?= $input ?>" style="max-width:20rem">
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <?php foreach ($styleLabels as $style => $label): ?>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400"><?= e($label) ?><?= $style === 'regular' ? ' *' : '' ?></label>
                        <input type="file" name="file_<?= $style ?>" accept=".ttf,font/ttf" <?= $style === 'regular' ? 'required' : '' ?> class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Hochladen</button>
        </form>
    </details>
</div>

<script>
(function () {
    var body = document.getElementById('role_body');
    function family(select) {
        var value = select.value || (select !== body ? body.value : '');
        return value ? "'pdf-" + value + "', Helvetica, Arial, sans-serif" : 'Helvetica, Arial, sans-serif';
    }
    function update() {
        document.querySelectorAll('[data-font-select]').forEach(function (select) {
            var preview = document.getElementById(select.getAttribute('data-preview'));
            if (preview) { preview.style.fontFamily = family(select); }
        });
    }
    document.querySelectorAll('[data-font-select]').forEach(function (select) { select.addEventListener('change', update); });
    update();

    // Vorschau Oberfläche: ohne Auswahl "Text" bzw. System-/Festbreitenschrift.
    var uiBody = document.getElementById('ui_body');
    var SYSTEM = 'ui-sans-serif, system-ui, sans-serif';
    var MONO = 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace';
    function uiFamily(select) {
        var role = select.getAttribute('data-role');
        var value = select.value || (role !== 'ui_body' && role !== 'ui_numbers' && uiBody ? uiBody.value : '');
        var fallback = role === 'ui_numbers' ? MONO : SYSTEM;
        return value ? "'pdf-" + value + "', " + fallback : fallback;
    }
    function updateUi() {
        document.querySelectorAll('[data-ui-font-select]').forEach(function (select) {
            var preview = document.getElementById(select.getAttribute('data-preview'));
            if (preview) { preview.style.fontFamily = uiFamily(select); }
        });
    }
    document.querySelectorAll('[data-ui-font-select]').forEach(function (select) { select.addEventListener('change', updateUi); });
    updateUi();
    document.querySelectorAll('[data-loading-text]').forEach(function (button) {
        button.closest('form').addEventListener('submit', function () { button.disabled = true; button.textContent = button.getAttribute('data-loading-text'); });
    });
})();
</script>
