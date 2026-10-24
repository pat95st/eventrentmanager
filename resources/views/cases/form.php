<?php
/** @var \App\Domain\CaseItem\CaseItem|null $case */
/** @var \App\Domain\Manufacturer\Manufacturer[] $manufacturers */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */

use function App\Infrastructure\Http\e;

$val = fn (string $field, ?string $fallback = null) => $old[$field] ?? $fallback ?? '';
?>

<div class="mb-6">
    <a href="<?= $case !== null ? '/cases/' . e($case->id) : '/cases' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Zurück</a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= $case !== null ? 'Case bearbeiten' : 'Neues Case' ?></h1>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/cases/' . e($editingId) : '/cases' ?>"
    class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div>
        <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name *</label>
        <input type="text" id="name" name="name" required
               value="<?= e($val('name', $case?->name)) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
    </div>

    <div>
        <label for="manufacturer_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Hersteller *</label>
        <select id="manufacturer_id" name="manufacturer_id" required
                class="mt-1 w-full rounded-md border <?= isset($errors['manufacturerId']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <option value="">Bitte wählen…</option>
            <?php $selectedManufacturer = $val('manufacturer_id', $case?->manufacturerId); ?>
            <?php foreach ($manufacturers as $manufacturer): ?>
                <option value="<?= e($manufacturer->id) ?>" <?= $selectedManufacturer === $manufacturer->id ? 'selected' : '' ?>><?= e($manufacturer->name) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['manufacturerId'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['manufacturerId']) ?></p><?php endif; ?>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Neue Hersteller lassen sich unter „Hersteller“ anlegen.</p>
    </div>

    <div>
        <label for="prefix" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Case-Präfix *</label>
        <input type="text" id="prefix" name="prefix" required maxlength="10" placeholder="z. B. RCK"
               value="<?= e($val('prefix', $case?->prefix)) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['prefix']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 font-mono text-sm uppercase focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['prefix'])): ?>
            <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['prefix']) ?></p>
        <?php else: ?>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Zusammen mit dem Hersteller-Präfix Grundlage für die Inventurnummern der einzelnen Case-Exemplare (HERSTELLER-CASE-NUMMER).</p>
        <?php endif; ?>
    </div>

    <div>
        <label for="description" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Beschreibung</label>
        <textarea id="description" name="description" rows="4"
                  class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"
        ><?= e($val('description', $case?->description)) ?></textarea>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            Speichern
        </button>
        <a href="<?= $case !== null ? '/cases/' . e($case->id) : '/cases' ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
    </div>
</form>

<?php if ($case !== null): ?>
    <p class="mt-4 max-w-2xl text-xs text-slate-400 dark:text-slate-500">
        Case-Exemplare (Inventurnummern) und deren Inhalt werden auf der Case-Detailseite verwaltet.
    </p>
<?php endif; ?>
