<?php
/** @var \App\Domain\Customer\Customer $customer */
/** @var \App\Domain\Messaging\MessagingSettings|null $messagingSettings */

use App\Domain\Messaging\MessagingChannel;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$hasPhone = $customer->phone !== null && trim($customer->phone) !== '';
$hasTelegramChatId = $customer->telegramChatId !== null && trim($customer->telegramChatId) !== '';

/** @var array<string, bool> $availableChannels [Kanal-Wert => ob Kontaktdaten UND Kanal eingerichtet sind] */
$availableChannels = [
    MessagingChannel::WhatsApp->value => $hasPhone && $messagingSettings?->isChannelReady(MessagingChannel::WhatsApp) === true,
    MessagingChannel::Telegram->value => $hasTelegramChatId && $messagingSettings?->isChannelReady(MessagingChannel::Telegram) === true,
    MessagingChannel::Signal->value => $hasPhone && $messagingSettings?->isChannelReady(MessagingChannel::Signal) === true,
];
$anyAvailable = in_array(true, $availableChannels, true);
$preselectedChannel = array_search(true, $availableChannels, true) ?: null;
?>

<div class="mt-6 max-w-2xl rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('customers.send_message.title')) ?></h2>
    <p class="mb-4 text-xs text-slate-400"><?= e(t('customers.send_message.hint')) ?></p>

    <?php if (!$anyAvailable): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('customers.send_message.none_available')) ?></p>
    <?php else: ?>
        <form method="post" action="/kunden/<?= e($customer->id) ?>/nachricht" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.send_message.field.channel')) ?></label>
                <div class="mt-1 flex gap-4">
                    <?php foreach (MessagingChannel::cases() as $option): ?>
                        <label class="inline-flex items-center gap-2 text-sm <?= $availableChannels[$option->value] ? 'text-slate-700 dark:text-slate-300' : 'cursor-not-allowed text-slate-300 dark:text-slate-600' ?>">
                            <input type="radio" name="channel" value="<?= e($option->value) ?>"
                                   <?= $availableChannels[$option->value] ? '' : 'disabled' ?>
                                   <?= $option->value === $preselectedChannel ? 'checked' : '' ?>>
                            <?= e($option->label()) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div>
                <label for="message_text" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.send_message.field.text')) ?></label>
                <textarea id="message_text" name="message_text" rows="4" required
                          class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700"></textarea>
                <p class="mt-1 text-xs text-slate-400"><?= e(t('customers.send_message.field.text_whatsapp_hint')) ?></p>
            </div>

            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                <?= e(t('customers.send_message.submit')) ?>
            </button>
        </form>
    <?php endif; ?>
</div>
