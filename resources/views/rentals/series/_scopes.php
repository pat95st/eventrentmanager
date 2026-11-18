<?php
/**
 * Checkboxes for the areas to take over.
 *
 * @var string[] $scopes selected CopyScope values
 */

use App\Domain\RentalSeries\CopyScope;
use function App\Infrastructure\Http\e;

?>
<fieldset>
    <legend class="text-sm font-semibold text-slate-700 dark:text-slate-300">Übernehmen</legend>
    <div class="mt-2 grid gap-1.5 text-sm sm:grid-cols-2">
        <?php foreach (CopyScope::cases() as $scope): ?>
            <label class="flex items-start gap-2">
                <input type="checkbox" name="scopes[]" value="<?= e($scope->value) ?>" class="mt-0.5"<?= in_array($scope->value, $scopes, true) ? ' checked' : '' ?>>
                <span><?= e($scope->label()) ?></span>
            </label>
        <?php endforeach; ?>
    </div>
    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Nie übernommen werden ausgegebene Inventarnummern, Rückgaben, Unterschriften, Checklisten-Links, Audio-Dateien sowie Angebote, Auftragsbestätigungen und Rechnungen.</p>
</fieldset>
