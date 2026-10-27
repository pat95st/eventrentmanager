<?php
/** @var \App\Domain\Customer\Customer|null $customer */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */
/** @var string|null $editingId */

use App\Domain\Customer\CustomerType;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $field, ?string $fallback = null) => array_key_exists($field, $old) ? ($old[$field] ?? '') : ($fallback ?? '');
$selectedType = $val('type', $customer?->type->value) ?: CustomerType::Privat->value;
?>

<div class="mb-6">
    <a href="/kunden" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('customers.form.back')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100">
        <?= $customer !== null ? e(t('customers.edit')) : e(t('customers.new')) ?>
        <?php if ($customer !== null): ?>
            <span class="ml-2 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-mono font-normal text-slate-500 dark:bg-slate-800 dark:text-slate-400"><?= e($customer->customerNumber) ?></span>
        <?php endif; ?>
    </h1>
</div>

<form
    method="post"
    action="<?= $editingId !== null ? '/kunden/' . e($editingId) : '/kunden' ?>"
    class="max-w-3xl space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
>
    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.type')) ?></label>
        <div class="mt-1 flex gap-4">
            <?php foreach (CustomerType::cases() as $type): ?>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="radio" name="type" value="<?= e($type->value) ?>" class="customer-type-radio"
                           <?= $selectedType === $type->value ? 'checked' : '' ?>>
                    <?= e($type->label()) ?>
                </label>
            <?php endforeach; ?>
        </div>
        <?php if (isset($errors['type'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['type']) ?></p><?php endif; ?>
    </div>

    <div id="company-name-field" class="<?= $selectedType === CustomerType::Geschaeftlich->value ? '' : 'hidden' ?>">
        <label for="company_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.company_name')) ?></label>
        <input type="text" id="company_name" name="company_name"
               value="<?= e($val('company_name', $customer?->companyName)) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['companyName']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['companyName'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['companyName']) ?></p><?php endif; ?>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="first_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.first_name')) ?></label>
            <input type="text" id="first_name" name="first_name" required
                   value="<?= e($val('first_name', $customer?->firstName)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['firstName']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['firstName'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['firstName']) ?></p><?php endif; ?>
        </div>
        <div>
            <label for="last_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.last_name')) ?></label>
            <input type="text" id="last_name" name="last_name" required
                   value="<?= e($val('last_name', $customer?->lastName)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['lastName']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['lastName'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['lastName']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="sm:col-span-2">
            <label for="street" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.street')) ?></label>
            <input type="text" id="street" name="street" required
                   value="<?= e($val('street', $customer?->street)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['street']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['street'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['street']) ?></p><?php endif; ?>
        </div>

        <div>
            <label for="address_line2" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.address_line2')) ?></label>
            <input type="text" id="address_line2" name="address_line2" placeholder="z. B. c/o, Etage"
                   value="<?= e($val('address_line2', $customer?->addressLine2)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <label for="postal_code" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.postal_code')) ?></label>
            <input type="text" id="postal_code" name="postal_code" required
                   value="<?= e($val('postal_code', $customer?->postalCode)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['postalCode']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['postalCode'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['postalCode']) ?></p><?php endif; ?>
        </div>
        <div class="col-span-2">
            <label for="city" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.city')) ?></label>
            <input type="text" id="city" name="city" required
                   value="<?= e($val('city', $customer?->city)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['city']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['city'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['city']) ?></p><?php endif; ?>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
            <label for="phone" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.phone')) ?></label>
            <input type="text" id="phone" name="phone"
                   value="<?= e($val('phone', $customer?->phone)) ?>"
                   class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <div>
            <label for="email" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.email')) ?></label>
            <input type="email" id="email" name="email"
                   value="<?= e($val('email', $customer?->email)) ?>"
                   class="mt-1 w-full rounded-md border <?= isset($errors['email']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
            <?php if (isset($errors['email'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['email']) ?></p><?php endif; ?>
        </div>
    </div>

    <div>
        <label for="telegram_chat_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.telegram_chat_id')) ?></label>
        <input type="text" id="telegram_chat_id" name="telegram_chat_id" inputmode="numeric"
               value="<?= e($val('telegram_chat_id', $customer?->telegramChatId)) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['telegramChatId']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <p class="mt-1 text-xs text-slate-400"><?= e(t('customers.form.telegram_chat_id_hint')) ?></p>
        <?php if (isset($errors['telegramChatId'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['telegramChatId']) ?></p><?php endif; ?>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
        <a href="/kunden" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></a>
    </div>
</form>

<?php if ($customer !== null): ?>
    <?php require __DIR__ . '/_send-message-box.php'; ?>
<?php endif; ?>

<script>
  // Firmenname wird nur bei Art "Geschäftlich" benötigt; Feld dementsprechend ein-/ausblenden.
  (function () {
    var radios = document.querySelectorAll('.customer-type-radio');
    var companyField = document.getElementById('company-name-field');

    function sync() {
      var checked = document.querySelector('.customer-type-radio:checked');
      var isBusiness = checked && checked.value === '<?= e(CustomerType::Geschaeftlich->value) ?>';
      companyField.classList.toggle('hidden', !isBusiness);
    }

    radios.forEach(function (radio) {
      radio.addEventListener('change', sync);
    });
  })();
</script>
