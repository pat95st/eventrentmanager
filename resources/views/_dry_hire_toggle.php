<?php
/**
 * Checkbox „Dry-Hire-Preise berechnen“ für Angebot/AB/Rechnung (Migration 141).
 * Erwartet $dryHire (Ergebnis von DryHireLookup::formData()).
 *
 * @var array{tiers: list<array{from: int, pct: float}>, customers: list<string>, prices: array<string, string>, checked: bool, available: bool} $dryHire
 */

use function App\Infrastructure\Http\e;

if (!($dryHire['available'] ?? false)) {
    return;
}
?>
<div data-dry-hire-wrap hidden class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm dark:border-amber-800 dark:bg-amber-950/30" style="margin-top:.5rem">
    <input type="hidden" name="dry_hire_section" value="1">
    <label class="flex items-start gap-2 text-amber-900 dark:text-amber-200">
        <input type="checkbox" name="dry_hire" value="1" data-dry-hire-checkbox class="mt-0.5 h-4 w-4 rounded border-slate-300">
        <span><strong>Dry-Hire-Preise berechnen</strong>
            <span data-dry-hire-badge class="block text-xs" style="opacity:.85">Der Kunde ist als Dry-Hire-Kunde hinterlegt. Ohne Häkchen werden die normalen Preise vorgeschlagen.</span>
        </span>
    </label>
    <p data-dry-hire-summary hidden class="mt-1 text-xs" style="color:#b45309"></p>
</div>
<script>window.DRY_HIRE_DATA = <?= json_encode($dryHire, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) ?>;</script>
