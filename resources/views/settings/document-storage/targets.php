<?php
/** @var \App\Domain\DocumentStorage\DocumentStorageTarget[] $targets */
/** @var \App\Domain\DocumentStorage\DocumentStorageTarget|null $editing */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var \App\Domain\DocumentStorage\StorageProtocol[] $protocols */

use App\Domain\DocumentStorage\StorageProtocol;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$selectedProtocol = StorageProtocol::tryFrom((string) ($old['protocol'] ?? $editing?->protocol->value ?? StorageProtocol::WebDav->value)) ?? StorageProtocol::WebDav;
?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.document_storage.targets.title')) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.document_storage.targets.subtitle')) ?></p>
    </div>
    <a href="/einstellungen/speicherziele/zuordnungen" class="text-sm font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('settings.document_storage.assignments.title')) ?> &rarr;</a>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <?php if ($targets === []): ?>
            <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.document_storage.targets.empty')) ?></p>
        <?php else: ?>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($targets as $target): ?>
                    <?php $isEditing = $editing !== null && $editing->id === $target->id; ?>
                    <div class="flex flex-wrap items-center justify-between gap-2 px-2 py-3 <?= $isEditing ? 'bg-brand-50 rounded-md ring-1 ring-brand-200 dark:bg-brand-950/40 dark:ring-brand-800' : '' ?>">
                        <div>
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-200">
                                <?= e($target->name) ?>
                                <?php if (!$target->enabled): ?>
                                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e(t('common.disabled')) ?></span>
                                <?php endif; ?>
                            </p>
                            <p class="text-xs text-slate-400 dark:text-slate-500">
                                <?= e($target->protocol->label()) ?>
                                <?php if ($target->host !== null): ?>
                                    · <?= e($target->host) ?><?= $target->port !== null ? ':' . e((string) $target->port) : '' ?>
                                <?php elseif ($target->s3Bucket !== null): ?>
                                    · <?= e($target->s3Bucket) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <details class="relative inline-block text-left">
                            <summary class="flex h-7 w-7 cursor-pointer list-none items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M10 3a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 8.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3zM10 14a1.5 1.5 0 110 3 1.5 1.5 0 010-3z"/></svg>
                                <span class="sr-only">Aktionen</span>
                            </summary>
                            <div class="absolute right-0 top-full z-10 mt-1 w-52 rounded-md border border-slate-200 bg-white py-1 text-left shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                <form method="post" action="/einstellungen/speicherziele/<?= e($target->id) ?>/testen">
                                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('settings.document_storage.targets.test_connection')) ?></button>
                                </form>
                                <a href="/einstellungen/speicherziele/<?= e($target->id) ?>/bearbeiten" class="block px-3 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.edit')) ?></a>
                                <form method="post" action="/einstellungen/speicherziele/<?= e($target->id) ?>/loeschen" data-confirm="<?= e(t('settings.document_storage.targets.confirm_delete')) ?>" data-confirm-style="danger">
                                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('common.delete')) ?></button>
                                </form>
                            </div>
                        </details>
                    </div>
                    <?php if ($target->protocol === StorageProtocol::Sftp || $target->protocol === StorageProtocol::Scp): ?>
                        <?php if (!extension_loaded('ssh2')): ?>
                            <p class="px-2 pb-2 text-xs text-amber-600 dark:text-amber-400"><?= e(t('settings.document_storage.targets.ssh2_missing_hint')) ?></p>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">
            <?= $editing !== null ? e(t('settings.document_storage.targets.edit')) : e(t('settings.document_storage.targets.new')) ?>
        </h2>
        <form
            method="post"
            action="<?= $editing !== null ? '/einstellungen/speicherziele/' . e($editing->id) : '/einstellungen/speicherziele' ?>"
            class="space-y-4"
        >
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

            <div>
                <label for="name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.name')) ?> *</label>
                <input type="text" id="name" name="name" required
                       value="<?= e($old['name'] ?? $editing?->name ?? '') ?>"
                       placeholder="z. B. Nextcloud Buchhaltung"
                       class="mt-1 w-full rounded-md border <?= isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p><?php endif; ?>
            </div>

            <div>
                <label for="protocol" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.protocol')) ?> *</label>
                <select id="protocol" name="protocol" data-protocol-select
                        class="mt-1 w-full rounded-md border <?= isset($errors['protocol']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php foreach ($protocols as $protocol): ?>
                        <option value="<?= e($protocol->value) ?>" <?= $selectedProtocol === $protocol ? 'selected' : '' ?>>
                            <?= e($protocol->label()) ?><?= in_array($protocol, [StorageProtocol::Sftp, StorageProtocol::Scp], true) && !extension_loaded('ssh2') ? ' (' . e(t('settings.document_storage.targets.ssh2_missing_short')) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['protocol'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['protocol']) ?></p><?php endif; ?>
            </div>

            <!-- WebDAV / FTPS / SFTP / SCP: Host+Port -->
            <div data-protocol-group="webdav,ftps,sftp,scp" class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <label for="host" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.host')) ?></label>
                    <input type="text" id="host" name="host" placeholder="z. B. cloud.beispiel.de"
                           value="<?= e($old['host'] ?? $editing?->host ?? '') ?>"
                           class="mt-1 w-full rounded-md border <?= isset($errors['host']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php if (isset($errors['host'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['host']) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="port" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.port')) ?></label>
                    <input type="number" id="port" name="port" min="1" max="65535"
                           value="<?= e((string) ($old['port'] ?? $editing?->port ?? '')) ?>"
                           class="mt-1 w-full rounded-md border <?= isset($errors['port']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php if (isset($errors['port'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['port']) ?></p><?php endif; ?>
                </div>
            </div>

            <!-- WebDAV / FTPS: Benutzername + Passwort -->
            <div data-protocol-group="webdav,ftps" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="username" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.username')) ?></label>
                    <input type="text" id="username" name="username" value="<?= e($old['username'] ?? $editing?->username ?? '') ?>"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                </div>
                <div>
                    <label for="password" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.password')) ?></label>
                    <input type="password" id="password" name="password" placeholder="<?= $editing?->password !== null ? e(t('settings.document_storage.targets.field.password_placeholder')) : '' ?>"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                </div>
            </div>

            <!-- SFTP / SCP: Benutzername + Passwort ODER privater Schlüssel -->
            <div data-protocol-group="sftp,scp" class="space-y-4">
                <div>
                    <label for="username_ssh" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.username')) ?></label>
                    <input type="text" id="username_ssh" name="username" value="<?= e($old['username'] ?? $editing?->username ?? '') ?>"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                </div>
                <div>
                    <label for="password_ssh" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.password_or_key_hint')) ?></label>
                    <input type="password" id="password_ssh" name="password" placeholder="<?= $editing?->password !== null ? e(t('settings.document_storage.targets.field.password_placeholder')) : '' ?>"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                    <?php if (isset($errors['password'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['password']) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="private_key" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.private_key')) ?></label>
                    <textarea id="private_key" name="private_key" rows="4" placeholder="<?= $editing?->privateKey !== null ? e(t('settings.document_storage.targets.field.private_key_placeholder')) : '-----BEGIN OPENSSH PRIVATE KEY-----' ?>"
                              class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"></textarea>
                </div>
                <div>
                    <label for="private_key_passphrase" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.private_key_passphrase')) ?></label>
                    <input type="password" id="private_key_passphrase" name="private_key_passphrase"
                           class="mt-1 w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                </div>
                <?php if (!extension_loaded('ssh2')): ?>
                    <p class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700 dark:bg-amber-950/30 dark:text-amber-400"><?= e(t('settings.document_storage.targets.ssh2_missing_hint')) ?></p>
                <?php endif; ?>
                <p data-protocol-group="scp" class="rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e(t('settings.document_storage.targets.scp_no_mkdir_hint')) ?></p>
            </div>

            <!-- WebDAV / FTPS / SFTP / SCP: Basis-Pfad -->
            <div data-protocol-group="webdav,ftps,sftp,scp">
                <label for="base_path" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.base_path')) ?></label>
                <input type="text" id="base_path" name="base_path" placeholder="/remote.php/dav/files/benutzername/"
                       value="<?= e($old['base_path'] ?? $editing?->basePath ?? '') ?>"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.document_storage.targets.field.base_path_hint')) ?></p>
            </div>

            <!-- WebDAV: HTTPS + Zertifikat -->
            <div data-protocol-group="webdav" class="space-y-2">
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="webdav_use_https" value="1" <?= (bool) ($old['webdav_use_https'] ?? $editing?->webdavUseHttps ?? true) ? 'checked' : '' ?>
                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                    <?= e(t('settings.document_storage.targets.field.webdav_use_https')) ?>
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="webdav_allow_self_signed" value="1" <?= (bool) ($old['webdav_allow_self_signed'] ?? $editing?->webdavAllowSelfSigned ?? false) ? 'checked' : '' ?>
                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                    <?= e(t('settings.document_storage.targets.field.webdav_allow_self_signed')) ?>
                </label>
            </div>

            <!-- S3 -->
            <div data-protocol-group="s3" class="space-y-4">
                <div>
                    <label for="s3_access_key_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.s3_access_key_id')) ?></label>
                    <input type="text" id="s3_access_key_id" name="s3_access_key_id" value="<?= e($old['s3_access_key_id'] ?? $editing?->s3AccessKeyId ?? '') ?>"
                           class="mt-1 w-full rounded-md border <?= isset($errors['s3AccessKeyId']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 font-mono text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php if (isset($errors['s3AccessKeyId'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['s3AccessKeyId']) ?></p><?php endif; ?>
                </div>
                <div>
                    <label for="s3_secret_access_key" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.s3_secret_access_key')) ?></label>
                    <input type="password" id="s3_secret_access_key" name="s3_secret_access_key" placeholder="<?= $editing?->s3SecretAccessKey !== null ? e(t('settings.document_storage.targets.field.password_placeholder')) : '' ?>"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 font-mono text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                </div>
                <div>
                    <label for="s3_bucket" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.s3_bucket')) ?></label>
                    <input type="text" id="s3_bucket" name="s3_bucket" value="<?= e($old['s3_bucket'] ?? $editing?->s3Bucket ?? '') ?>"
                           class="mt-1 w-full rounded-md border <?= isset($errors['s3Bucket']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                    <?php if (isset($errors['s3Bucket'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['s3Bucket']) ?></p><?php endif; ?>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="s3_region" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.s3_region')) ?></label>
                        <input type="text" id="s3_region" name="s3_region" value="<?= e($old['s3_region'] ?? $editing?->s3Region ?? 'eu-central-1') ?>"
                               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                    </div>
                    <div>
                        <label for="s3_endpoint" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.document_storage.targets.field.s3_endpoint')) ?></label>
                        <input type="text" id="s3_endpoint" name="s3_endpoint" placeholder="z. B. s3.eu-central-3.hetzner.cloud"
                               value="<?= e($old['s3_endpoint'] ?? $editing?->s3Endpoint ?? '') ?>"
                               class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="s3_use_path_style" value="1" <?= (bool) ($old['s3_use_path_style'] ?? $editing?->s3UsePathStyle ?? false) ? 'checked' : '' ?>
                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                    <?= e(t('settings.document_storage.targets.field.s3_use_path_style')) ?>
                </label>
                <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.document_storage.targets.field.s3_endpoint_hint')) ?></p>
            </div>

            <div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="enabled" value="1" <?= (bool) ($old['enabled'] ?? $editing?->enabled ?? true) ? 'checked' : '' ?>
                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                    <?= e(t('settings.document_storage.targets.field.enabled')) ?>
                </label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                    <?= e(t('common.save')) ?>
                </button>
                <?php if ($editing !== null): ?>
                    <a href="/einstellungen/speicherziele" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var select = document.querySelector('[data-protocol-select]');
    if (!select) return;

    function applyVisibility() {
        var protocol = select.value;
        document.querySelectorAll('[data-protocol-group]').forEach(function (el) {
            var allowed = el.getAttribute('data-protocol-group').split(',');
            el.style.display = allowed.indexOf(protocol) !== -1 ? '' : 'none';
        });
    }

    select.addEventListener('change', applyVisibility);
    applyVisibility();
})();
</script>
