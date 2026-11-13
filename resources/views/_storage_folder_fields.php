<?php
/**
 * Formularbaustein „Ordner im Dateiablagesystem“ für Veranstaltungen und
 * Kunden (Migration 148). Erwartet $storageFolder (Ergebnis von
 * StorageFolderForm::formData()); bei Veranstaltungen muss das umgebende
 * Formular das Kundenfeld "customer_id" enthalten.
 *
 * @var array<string, mixed> $storageFolder
 */

use function App\Infrastructure\Http\e;

if (!($storageFolder['available'] ?? false)) {
    return;
}

/** @var \App\Domain\DocumentStorage\StorageFolder|null $sfFolder */
$sfFolder = $storageFolder['folder'];
/** @var \App\Domain\DocumentStorage\DocumentStorageTarget|null $sfFolderTarget */
$sfFolderTarget = $storageFolder['folderTarget'];
$sfIsRental = $storageFolder['ownerType'] === 'rental';
$sfTargets = $storageFolder['targets'];
$sfExistingSharing = $sfFolderTarget !== null && $sfFolderTarget->protocol->supportsSharing();
$sfInputClass = 'mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700';
$sfHintClass = 'block text-xs text-slate-400 dark:text-slate-500';
?>
<div data-storage-folder class="rounded-md border border-slate-200 p-4 dark:border-slate-800">
    <input type="hidden" name="storage_folder_section" value="1">
    <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
        <input type="checkbox" name="storage_folder_enabled" value="1" class="mt-0.5" data-sf-enabled <?= $storageFolder['enabled'] ? 'checked' : '' ?>>
        <span>
            Ordner im Dateiablagesystem anlegen
            <span class="<?= $sfHintClass ?>">
                <?php if ($sfFolder !== null): ?>
                    Wird der Haken entfernt, löst sich nur die Verknüpfung (eine Freigabe wird aufgehoben) – der Ordner und sein Inhalt bleiben erhalten.
                <?php else: ?>
                    Der Ordner wird beim Speichern nach der in den Einstellungen hinterlegten Namensregel angelegt.
                <?php endif; ?>
            </span>
        </span>
    </label>

    <div data-sf-details class="mt-3 space-y-3 pl-6" <?= $storageFolder['enabled'] ? '' : 'hidden' ?>>
        <?php if ($sfFolder !== null): ?>
            <p class="text-sm text-slate-700 dark:text-slate-300">
                <span class="block text-xs font-medium text-slate-600 dark:text-slate-400">Ordner<?= $sfFolderTarget !== null ? ' auf „' . e($sfFolderTarget->name) . '“' : '' ?></span>
                <span class="font-mono text-xs break-all"><?= e($sfFolder->path) ?></span>
            </p>
        <?php else: ?>
            <?php if ($sfIsRental): ?>
                <?php // Eigener Wrapper: "hidden" wirkt nicht auf Elementen mit der Klasse "flex". ?>
                <div data-sf-customer-wrap hidden>
                    <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="storage_folder_in_customer_folder" value="1" class="mt-0.5" data-sf-in-customer <?= $storageFolder['inCustomerFolder'] ? 'checked' : '' ?>>
                        <span>
                            Als Unterordner im Kundenordner anlegen
                            <span class="<?= $sfHintClass ?>" data-sf-customer-hint></span>
                        </span>
                    </label>
                </div>
            <?php endif; ?>
            <div data-sf-target-wrap <?= count($sfTargets) > 1 ? '' : 'hidden' ?>>
                <label for="storage_folder_target_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Ziellaufwerk</label>
                <select id="storage_folder_target_id" name="storage_folder_target_id" data-sf-target class="<?= $sfInputClass ?> max-w-sm">
                    <?php foreach ($sfTargets as $sfTarget): ?>
                        <option value="<?= e($sfTarget['id']) ?>"
                                data-sharing="<?= $sfTarget['sharing'] ? '1' : '0' ?>"
                                data-always-password="<?= $sfTarget['alwaysPassword'] ? '1' : '0' ?>"
                                <?= $storageFolder['selectedTargetId'] === $sfTarget['id'] ? 'selected' : '' ?>><?= e($sfTarget['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div data-sf-share-wrap class="space-y-3" <?= $sfFolder !== null && !$sfExistingSharing ? 'hidden' : '' ?>>
            <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="storage_folder_share_enabled" value="1" class="mt-0.5" data-sf-share <?= $storageFolder['shareEnabled'] ? 'checked' : '' ?>>
                <span>
                    Ordner mit dem Kunden teilen (per E-Mail)
                    <span class="<?= $sfHintClass ?>">
                        <?php if ($sfFolder !== null && $sfFolder->isShared()): ?>
                            Wird der Haken entfernt, wird der Freigabe-Link beim Speichern ungültig.
                        <?php else: ?>
                            Beim Speichern wird der Ordner für die E-Mail-Adresse des Kunden freigegeben. Nextcloud verschickt die Einladung selbst; bei ownCloud geht der Freigabe-Link per E-Mail aus dieser Anwendung raus.
                        <?php endif; ?>
                    </span>
                </span>
            </label>

            <div data-sf-share-details class="space-y-3 pl-6" <?= $storageFolder['shareEnabled'] ? '' : 'hidden' ?>>
                <div>
                    <label for="storage_folder_share_permission" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Berechtigung des Kunden</label>
                    <select id="storage_folder_share_permission" name="storage_folder_share_permission" class="<?= $sfInputClass ?> max-w-sm">
                        <?php foreach ($storageFolder['permissions'] as $sfPermission): ?>
                            <option value="<?= e($sfPermission->value) ?>" <?= $storageFolder['sharePermission'] === $sfPermission->value ? 'selected' : '' ?>><?= e($sfPermission->label()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($sfFolder !== null && $sfFolder->isShared()): ?>
                    <p class="text-sm text-slate-700 dark:text-slate-300">
                        <span class="block text-xs font-medium text-slate-600 dark:text-slate-400">Freigabe-Link</span>
                        <a href="<?= e($sfFolder->shareUrl) ?>" target="_blank" rel="noopener" class="break-all text-brand-700 underline dark:text-brand-400"><?= e($sfFolder->shareUrl) ?></a>
                        <span class="<?= $sfHintClass ?>">
                            <?= $sfFolder->shareHasPassword ? 'Mit Passwort geschützt.' : 'Ohne Passwort.' ?>
                            <?php if ($sfFolder->shareMailedAt !== null): ?>
                                Zuletzt verschickt am <?= e($sfFolder->shareMailedAt->format('d.m.Y H:i')) ?> an <?= e($sfFolder->shareMailedTo) ?>.
                            <?php else: ?>
                                Noch nicht per E-Mail verschickt.
                            <?php endif; ?>
                        </span>
                    </p>
                    <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="storage_folder_regenerate_password" value="1" class="mt-0.5">
                        <span>
                            Neues Passwort generieren
                            <span class="<?= $sfHintClass ?>">Das neue Passwort wird nach dem Speichern einmalig angezeigt (mit der Frage, ob und wie es an den Kunden geht); <?= $sfFolder->shareHasPassword ? 'das bisherige gilt dann nicht mehr.' : 'die Freigabe ist danach passwortgeschützt.' ?></span>
                        </span>
                    </label>
                    <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <input type="checkbox" name="storage_folder_resend_mail" value="1" class="mt-0.5">
                        <span>Freigabe-Link erneut per E-Mail an den Kunden senden</span>
                    </label>
                <?php else: ?>
                    <p class="text-xs text-slate-500 dark:text-slate-400" data-sf-password-hint
                       data-existing-always="<?= $sfFolderTarget !== null && $sfFolderTarget->shareAlwaysPassword ? '1' : '0' ?>"></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var root = document.querySelector('[data-storage-folder]');
    if (!root) { return; }
    var CUSTOMER_FOLDERS = <?= json_encode((object) ($storageFolder['customerFolders'] ?? []), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
    var HAS_FOLDER = <?= $sfFolder !== null ? 'true' : 'false' ?>;
    var EXISTING_SHARING = <?= $sfExistingSharing ? 'true' : 'false' ?>;

    var enabled = root.querySelector('[data-sf-enabled]');
    var details = root.querySelector('[data-sf-details]');
    var target = root.querySelector('[data-sf-target]');
    var targetWrap = root.querySelector('[data-sf-target-wrap]');
    var customerWrap = root.querySelector('[data-sf-customer-wrap]');
    var inCustomer = root.querySelector('[data-sf-in-customer]');
    var customerHint = root.querySelector('[data-sf-customer-hint]');
    var shareWrap = root.querySelector('[data-sf-share-wrap]');
    var share = root.querySelector('[data-sf-share]');
    var shareDetails = root.querySelector('[data-sf-share-details]');
    var passwordHint = root.querySelector('[data-sf-password-hint]');
    var form = root.closest('form');
    var customerInput = form ? form.querySelector('input[name="customer_id"]') : null;

    function update() {
        details.hidden = !enabled.checked;

        var sharing = EXISTING_SHARING;
        var alwaysPassword = passwordHint ? passwordHint.getAttribute('data-existing-always') === '1' : false;

        if (!HAS_FOLDER) {
            var customerFolder = customerInput && Object.prototype.hasOwnProperty.call(CUSTOMER_FOLDERS, customerInput.value)
                ? CUSTOMER_FOLDERS[customerInput.value] : null;
            if (customerWrap) {
                customerWrap.hidden = customerFolder === null;
                if (customerFolder === null) { inCustomer.checked = false; }
                else { customerHint.textContent = 'Der Kunde hat einen Ordner auf „' + customerFolder.name + '“.'; }
            }
            var useCustomerFolder = customerFolder !== null && inCustomer && inCustomer.checked;
            if (targetWrap) { targetWrap.hidden = useCustomerFolder || target.options.length < 2; }

            if (useCustomerFolder) {
                sharing = customerFolder.sharing;
                alwaysPassword = customerFolder.alwaysPassword;
            } else {
                var option = target && target.selectedOptions.length ? target.selectedOptions[0] : null;
                sharing = option !== null && option.getAttribute('data-sharing') === '1';
                alwaysPassword = option !== null && option.getAttribute('data-always-password') === '1';
            }
        }

        shareWrap.hidden = !sharing;
        if (!sharing) { share.checked = false; }
        shareDetails.hidden = !share.checked;
        if (passwordHint) {
            passwordHint.textContent = alwaysPassword
                ? 'Die Freigabe wird mit einem automatisch erzeugten Passwort geschützt. Es wird nach dem Speichern einmalig angezeigt – dort werden Sie gefragt, ob und wie es an den Kunden geschickt wird.'
                : 'Die Freigabe wird ohne Passwort angelegt. Ein Passwort lässt sich später beim Bearbeiten erzeugen.';
        }
    }

    [enabled, target, inCustomer, share, customerInput].forEach(function (el) {
        if (el) { el.addEventListener('change', update); }
    });
    update();
})();
</script>
