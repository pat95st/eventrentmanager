<?php
/** @var \App\Domain\Settings\SmtpSettings $settings */
/** @var array<string, string> $errors */
/** @var string $testAddress */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.smtp.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.smtp.subtitle')) ?></p>
</div>

<form method="post" action="/einstellungen/smtp" class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <label for="host" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.smtp.field.host')) ?></label>
            <input type="text" id="host" name="host" required placeholder="z. B. smtp.office365.com"
                   value="<?= e($settings->host) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['host']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['host'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['host']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="port" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.smtp.field.port')) ?></label>
            <input type="number" id="port" name="port" required min="1" max="65535"
                   value="<?= e((string) $settings->port) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['port']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['port'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['port']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label for="encryption" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.smtp.field.encryption')) ?></label>
        <select id="encryption" name="encryption"
                class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <?php foreach (\App\Domain\Settings\SmtpEncryption::cases() as $option): ?>
                <option value="<?= e($option->value) ?>" <?= $settings->encryption === $option ? 'selected' : '' ?>><?= e($option->label()) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['encryption'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['encryption']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="username" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.smtp.field.username')) ?></label>
            <input type="text" id="username" name="username" value="<?= e($settings->username ?? '') ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <div>
            <label for="password" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.smtp.field.password')) ?></label>
            <input type="password" id="password" name="password" placeholder="<?= $settings->password !== null ? e(t('settings.smtp.field.password_placeholder')) : '' ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="from_address" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.smtp.field.from_address')) ?></label>
            <input type="email" id="from_address" name="from_address" required value="<?= e($settings->fromAddress) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['fromAddress']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['fromAddress'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['fromAddress']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="from_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.smtp.field.from_name')) ?></label>
            <input type="text" id="from_name" name="from_name" required value="<?= e($settings->fromName) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['fromName']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['fromName'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['fromName']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="reply_to_address" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.smtp.field.reply_to_address')) ?></label>
            <p class="mb-2 mt-0.5 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.smtp.field.reply_to_address_hint')) ?></p>
            <input type="email" id="reply_to_address" name="reply_to_address" value="<?= e($settings->replyToAddress ?? '') ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['replyToAddress']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['replyToAddress'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['replyToAddress']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="archive_bcc_address" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.smtp.field.archive_bcc_address')) ?></label>
            <p class="mb-2 mt-0.5 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.smtp.field.archive_bcc_address_hint')) ?></p>
            <input type="email" id="archive_bcc_address" name="archive_bcc_address" value="<?= e($settings->archiveBccAddress ?? '') ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['archiveBccAddress']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['archiveBccAddress'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['archiveBccAddress']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.smtp.field.archive_categories')) ?></label>
        <p class="mb-2 mt-0.5 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.smtp.field.archive_categories_hint')) ?></p>
        <?php
        $checkedCategories = $settings->archiveCategories ?? array_map(static fn ($case) => $case->value, \App\Domain\Mail\MailCategory::cases());
        ?>
        <div class="space-y-1.5">
            <?php foreach (\App\Domain\Mail\MailCategory::cases() as $case): ?>
                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                    <input type="checkbox" name="archive_categories[]" value="<?= e($case->value) ?>"
                           <?= in_array($case->value, $checkedCategories, true) ? 'checked' : '' ?>
                           class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                    <?= e($case->label()) ?>
                </label>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>

<div class="mt-6 max-w-2xl rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.smtp.test.title')) ?></h2>
    <?php if (isset($errors['testAddress'])): ?>
        <p class="mb-3 text-xs text-red-600 dark:text-red-400"><?= e($errors['testAddress']) ?></p>
    <?php endif; ?>
    <form method="post" action="/einstellungen/smtp/test" class="flex flex-wrap items-end gap-3">
      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <div class="flex-1">
            <label for="test_address" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.smtp.test.field.address')) ?></label>
            <input type="email" id="test_address" name="test_address" required value="<?= e($testAddress) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('settings.smtp.test.submit')) ?>
        </button>
    </form>
</div>
