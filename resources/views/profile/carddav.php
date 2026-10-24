<?php
/**
 * @var \App\Domain\AddressBook\CardDavCredential[] $credentials
 * @var array{addressBook: \App\Domain\AddressBook\AddressBook, url: string}[] $addressBookUrls
 * @var string $discoveryUrl
 * @var array<string, string> $errors
 * @var string|null $newRawPassword
 */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('profile.carddav.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('profile.carddav.subtitle')) ?></p>
</div>

<div class="max-w-2xl space-y-6">
    <!-- Verfügbare Adressbücher -->
    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('profile.carddav.addressbooks.title')) ?></h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('profile.carddav.addressbooks.hint')) ?></p>

        <?php if ($addressBookUrls === []): ?>
            <p class="text-sm text-slate-400 dark:text-slate-500"><?= e(t('profile.carddav.addressbooks.empty')) ?></p>
        <?php else: ?>
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($addressBookUrls as $entry): ?>
                    <li class="py-3">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($entry['addressBook']->label()) ?></p>
                        <p class="mt-1 select-all break-all rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 font-mono text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400"><?= e($entry['url']) ?></p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
            <?= e(t('profile.carddav.discovery_hint')) ?>
            <span class="select-all break-all font-mono"><?= e($discoveryUrl) ?></span>
        </p>
    </div>

    <!-- App-Passwörter -->
    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('profile.carddav.passwords.title')) ?></h2>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('profile.carddav.passwords.hint')) ?></p>

        <?php if ($newRawPassword !== null): ?>
            <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950/40">
                <p class="text-sm font-medium text-amber-800 dark:text-amber-300"><?= e(t('profile.carddav.passwords.new_password_hint')) ?></p>
                <p class="mt-2 select-all break-all rounded-md border border-amber-200 bg-white px-3 py-2 font-mono text-sm text-slate-800 dark:border-amber-800 dark:bg-slate-900 dark:text-slate-200"><?= e($newRawPassword) ?></p>
                <p class="mt-2 text-xs text-amber-700 dark:text-amber-400"><?= e(t('profile.carddav.passwords.username_hint', ['email' => \App\Infrastructure\Http\Auth\CurrentUser::get()->email])) ?></p>
            </div>
        <?php endif; ?>

        <ul class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($credentials as $credential): ?>
                <li class="flex items-center justify-between gap-3 py-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($credential->name) ?></p>
                        <p class="text-xs text-slate-400 dark:text-slate-500">
                            <?= e(t('profile.carddav.passwords.ending_in', ['last_four' => $credential->tokenLastFour])) ?>
                            &middot;
                            <?= $credential->lastUsedAt !== null
                                ? e(t('profile.carddav.passwords.last_used_on', ['date' => $credential->lastUsedAt->format('d.m.Y H:i')]))
                                : e(t('profile.carddav.passwords.never_used')) ?>
                        </p>
                    </div>
                    <form method="post" action="/profil/carddav/<?= e($credential->id) ?>/loeschen" data-confirm="<?= e(t('profile.carddav.passwords.confirm_delete')) ?>" data-confirm-style="danger">
                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                        <button type="submit" class="whitespace-nowrap rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40"><?= e(t('profile.carddav.passwords.delete')) ?></button>
                    </form>
                </li>
            <?php endforeach; ?>
            <?php if ($credentials === []): ?>
                <li class="py-3 text-sm text-slate-400 dark:text-slate-500"><?= e(t('profile.carddav.passwords.empty')) ?></li>
            <?php endif; ?>
        </ul>

        <form method="post" action="/profil/carddav" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('profile.carddav.passwords.name_label')) ?></label>
                <input type="text" name="name" placeholder="<?= e(t('profile.carddav.passwords.name_placeholder')) ?>"
                       class="mt-1 rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900">
                <?php if (isset($errors['name'])): ?><p class="mt-1 text-xs text-red-600"><?= e($errors['name']) ?></p><?php endif; ?>
            </div>
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('profile.carddav.passwords.create')) ?></button>
        </form>
    </div>
</div>
