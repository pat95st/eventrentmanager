<?php
/** @var \App\Domain\Settings\MailboxSettings $settings */
/** @var array<string, string> $errors */
/** @var \App\Domain\ReceiptInbox\ReceiptInboxAllowedSender[] $allowedSenders */
/** @var array<string, string> $allowedSenderErrors */
/** @var string $cronScriptPath */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.mailbox.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.mailbox.subtitle')) ?></p>
</div>

<form method="post" action="/einstellungen/mailserver" class="max-w-2xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div>
        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
            <input type="checkbox" name="enabled" value="1" <?= $settings->enabled ? 'checked' : '' ?>
                   class="rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
            <?= e(t('settings.mailbox.field.enabled')) ?>
        </label>
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.mailbox.field.enabled_hint')) ?></p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label for="protocol" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.mailbox.field.protocol')) ?></label>
            <select id="protocol" name="protocol" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                <?php foreach (\App\Domain\Settings\MailboxProtocol::cases() as $option): ?>
                    <option value="<?= e($option->value) ?>" <?= $settings->protocol === $option ? 'selected' : '' ?>><?= e($option->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sm:col-span-2">
            <label for="host" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.mailbox.field.host')) ?></label>
            <input type="text" id="host" name="host" placeholder="z. B. imap.beispiel.de"
                   value="<?= e($settings->host ?? '') ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['host']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['host'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['host']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="port" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.mailbox.field.port')) ?></label>
            <input type="number" id="port" name="port" min="1" max="65535" placeholder="993"
                   value="<?= e($settings->port !== null ? (string) $settings->port : '') ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['port']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['port'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['port']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="encryption" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.mailbox.field.encryption')) ?></label>
            <select id="encryption" name="encryption" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                <?php foreach (\App\Domain\Settings\MailboxEncryption::cases() as $option): ?>
                    <option value="<?= e($option->value) ?>" <?= $settings->encryption === $option ? 'selected' : '' ?>><?= e($option->label()) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="username" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.mailbox.field.username')) ?></label>
            <input type="text" id="username" name="username" value="<?= e($settings->username ?? '') ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <div>
            <label for="password" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.mailbox.field.password')) ?></label>
            <input type="password" id="password" name="password" placeholder="<?= $settings->password !== null ? e(t('settings.mailbox.field.password_placeholder')) : '' ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
    </div>

    <div>
        <label for="imap_folder" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.mailbox.field.imap_folder')) ?></label>
        <input type="text" id="imap_folder" name="imap_folder" value="<?= e($settings->imapFolder) ?>"
               class="mt-1 w-full max-w-xs rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.mailbox.field.imap_folder_hint')) ?></p>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>

<div class="mt-6 max-w-2xl rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.mailbox.test.title')) ?></h2>
    <p class="mb-3 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.mailbox.test.hint')) ?></p>
    <form method="post" action="/einstellungen/mailserver/testen">
      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            <?= e(t('settings.mailbox.test.submit')) ?>
        </button>
    </form>
</div>

<div class="mt-6 max-w-2xl rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.mailbox.allowed_senders.title')) ?></h2>
    <p class="mb-4 text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.mailbox.allowed_senders.hint')) ?></p>

    <?php if ($allowedSenders === []): ?>
        <p class="mb-4 text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.mailbox.allowed_senders.empty')) ?></p>
    <?php else: ?>
        <div class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($allowedSenders as $sender): ?>
                <div class="flex flex-wrap items-center justify-between gap-2 py-2">
                    <span class="text-sm text-slate-700 dark:text-slate-300">
                        <?= e($sender->email) ?>
                        <?php if ($sender->note !== null && trim($sender->note) !== ''): ?>
                            <span class="text-xs text-slate-400 dark:text-slate-500">— <?= e($sender->note) ?></span>
                        <?php endif; ?>
                    </span>
                    <form method="post" action="/einstellungen/mailserver/erlaubte-absender/<?= e($sender->id) ?>/loeschen" data-confirm="<?= e(t('settings.mailbox.allowed_senders.confirm_delete')) ?>" data-confirm-style="danger">
                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/einstellungen/mailserver/erlaubte-absender" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <div>
            <label for="email" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.mailbox.allowed_senders.field.email')) ?></label>
            <input type="email" id="email" name="email" required
                   class="mt-1 w-full rounded-md border <?= isset($allowedSenderErrors['email']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($allowedSenderErrors['email'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($allowedSenderErrors['email']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="note" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.mailbox.allowed_senders.field.note')) ?></label>
            <input type="text" id="note" name="note"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <div class="flex items-end">
            <button type="submit" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                <?= e(t('settings.mailbox.allowed_senders.submit')) ?>
            </button>
        </div>
    </form>
</div>

<div class="mt-6 max-w-2xl rounded-lg border border-amber-200 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-950/30">
    <h2 class="mb-2 text-sm font-semibold text-amber-800 dark:text-amber-300"><?= e(t('settings.mailbox.cron.title')) ?></h2>
    <p class="mb-3 text-xs text-amber-700 dark:text-amber-400"><?= e(t('settings.mailbox.cron.explanation')) ?></p>
    <p class="mb-1 text-xs font-medium text-amber-800 dark:text-amber-300"><?= e(t('settings.mailbox.cron.script_path_label')) ?></p>
    <code class="mb-3 block break-all rounded bg-white px-3 py-2 text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-300"><?= e($cronScriptPath) ?></code>
    <p class="mb-1 text-xs font-medium text-amber-800 dark:text-amber-300"><?= e(t('settings.mailbox.cron.example_label')) ?></p>
    <code class="block break-all rounded bg-white px-3 py-2 text-xs text-slate-700 dark:bg-slate-900 dark:text-slate-300">*/5 * * * * /usr/bin/php <?= e($cronScriptPath) ?> &gt;&gt; /dev/null 2&gt;&amp;1</code>
    <p class="mt-2 text-xs text-amber-700 dark:text-amber-400"><?= e(t('settings.mailbox.cron.php_binary_hint')) ?></p>
</div>
