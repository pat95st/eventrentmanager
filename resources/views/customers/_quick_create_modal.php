<?php
/**
 * "+ Neuer Kunde"-Modal für die Dokumentformulare (Rechnungen/Angebote/
 * Auftragsbestätigungen) — bewusst als EINE gemeinsame Partial statt pro
 * Formular dupliziert (anders als der sonstige Positions-/Zeilen-Code
 * dieser Views, der laut Konvention bewusst dupliziert wird): das Modal hat
 * keine Berührungspunkte mit dem jeweiligen Dokumenttyp und müsste bei einer
 * Änderung sonst an drei Stellen synchron gehalten werden. Legt per fetch()
 * einen Kunden über den bestehenden POST /kunden-Endpunkt an
 * (CustomerController::store(), AJAX-Zweig) und trägt bei Erfolg den neuen
 * Kunden direkt in das/die Live-Suche-Auswahlfeld(er) für customer_id ein
 * (siehe App\Infrastructure\Http\renderLiveSearchField(), Modus "select"),
 * ohne die Seite neu zu laden. Seit der Umstellung von <select> auf die
 * Live-Suche gibt es dafür kein eindeutiges Element mehr je Formular,
 * weshalb hier — anders als zuvor — ALLE passenden Widgets auf der Seite
 * befüllt werden (data-live-search[data-url="/kunden/suche"]); in der
 * Praxis kommt genau eines pro Seite vor.
 */

use App\Domain\Customer\CustomerType;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>
<div id="customer-quick-create-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4">
    <div class="flex max-h-[90vh] w-full max-w-lg flex-col rounded-lg bg-white shadow-xl dark:bg-slate-900">
        <div class="flex items-center justify-between border-b border-slate-200 p-4 dark:border-slate-800">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200"><?= e(t('customers.quick_create.title')) ?></h3>
            <button type="button" id="customer-quick-create-close" class="text-lg leading-none text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">&times;</button>
        </div>
        <div class="flex-1 space-y-4 overflow-y-auto p-4">
            <p id="customer-quick-create-error" class="hidden rounded-md border border-red-200 bg-red-50 p-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-400"></p>

            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.type')) ?></label>
                <div class="mt-1 flex gap-4">
                    <?php foreach (CustomerType::cases() as $type): ?>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                            <input type="radio" name="qc_type" value="<?= e($type->value) ?>" class="customer-quick-create-type-radio" <?= $type === CustomerType::Privat ? 'checked' : '' ?>>
                            <?= e($type->label()) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="customer-quick-create-company-field" class="hidden">
                <label for="qc_company_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.company_name')) ?></label>
                <input type="text" id="qc_company_name" name="qc_company_name"
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="qc_first_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.first_name')) ?></label>
                    <input type="text" id="qc_first_name" name="qc_first_name" required
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                </div>
                <div>
                    <label for="qc_last_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.last_name')) ?></label>
                    <input type="text" id="qc_last_name" name="qc_last_name" required
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                </div>
            </div>

            <div>
                <label for="qc_street" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.street')) ?></label>
                <input type="text" id="qc_street" name="qc_street" required
                       class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label for="qc_postal_code" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.postal_code')) ?></label>
                    <input type="text" id="qc_postal_code" name="qc_postal_code" required
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                </div>
                <div class="col-span-2">
                    <label for="qc_city" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.city')) ?></label>
                    <input type="text" id="qc_city" name="qc_city" required
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="qc_phone" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.phone')) ?></label>
                    <input type="text" id="qc_phone" name="qc_phone"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                </div>
                <div>
                    <label for="qc_email" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('customers.form.email')) ?></label>
                    <input type="email" id="qc_email" name="qc_email"
                           class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 border-t border-slate-200 p-4 dark:border-slate-800">
            <button type="button" id="customer-quick-create-cancel" class="text-sm text-slate-500 hover:underline dark:text-slate-400"><?= e(t('common.cancel')) ?></button>
            <button type="button" id="customer-quick-create-submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('customers.quick_create.save')) ?></button>
        </div>
    </div>
</div>

<script>
(function () {
    var GESCHAEFTLICH = <?= json_encode(CustomerType::Geschaeftlich->value, JSON_THROW_ON_ERROR) ?>;
    var ERROR_GENERIC = <?= json_encode(t('customers.quick_create.error_generic')) ?>;

    var modal = document.getElementById('customer-quick-create-modal');
    if (!modal) { return; }

    var openButtons = document.querySelectorAll('[data-customer-quick-create-open]');
    var closeButton = document.getElementById('customer-quick-create-close');
    var cancelButton = document.getElementById('customer-quick-create-cancel');
    var submitButton = document.getElementById('customer-quick-create-submit');
    var errorBox = document.getElementById('customer-quick-create-error');
    var companyField = document.getElementById('customer-quick-create-company-field');
    var typeRadios = document.querySelectorAll('.customer-quick-create-type-radio');

    function syncCompanyField() {
        var checked = document.querySelector('.customer-quick-create-type-radio:checked');
        companyField.classList.toggle('hidden', !checked || checked.value !== GESCHAEFTLICH);
    }
    typeRadios.forEach(function (radio) { radio.addEventListener('change', syncCompanyField); });

    function resetForm() {
        ['qc_company_name', 'qc_first_name', 'qc_last_name', 'qc_street', 'qc_postal_code', 'qc_city', 'qc_phone', 'qc_email'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.value = ''; }
        });
        var privatRadio = document.querySelector('.customer-quick-create-type-radio[value]:not([value="' + GESCHAEFTLICH + '"])');
        if (privatRadio) { privatRadio.checked = true; }
        syncCompanyField();
        errorBox.classList.add('hidden');
        errorBox.textContent = '';
    }

    function openModal() {
        resetForm();
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        window.setTimeout(function () {
            var firstInput = document.getElementById('qc_first_name');
            if (firstInput) { firstInput.focus(); }
        }, 0);
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    openButtons.forEach(function (button) { button.addEventListener('click', openModal); });
    closeButton.addEventListener('click', closeModal);
    cancelButton.addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) { closeModal(); } });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) { closeModal(); }
    });

    submitButton.addEventListener('click', function () {
        var typeRadio = document.querySelector('.customer-quick-create-type-radio:checked');
        var params = new URLSearchParams();
        params.set('csrf_token', window.CSRF_TOKEN);
        params.set('type', typeRadio ? typeRadio.value : '');
        params.set('company_name', document.getElementById('qc_company_name').value);
        params.set('first_name', document.getElementById('qc_first_name').value);
        params.set('last_name', document.getElementById('qc_last_name').value);
        params.set('street', document.getElementById('qc_street').value);
        params.set('postal_code', document.getElementById('qc_postal_code').value);
        params.set('city', document.getElementById('qc_city').value);
        params.set('phone', document.getElementById('qc_phone').value);
        params.set('email', document.getElementById('qc_email').value);

        submitButton.disabled = true;
        errorBox.classList.add('hidden');

        fetch('/kunden', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': window.CSRF_TOKEN,
            },
            body: params.toString(),
        }).then(function (response) {
            return response.json().then(function (data) { return { ok: response.ok, data: data }; });
        }).then(function (result) {
            submitButton.disabled = false;

            if (!result.ok) {
                var errors = (result.data && result.data.errors) || {};
                var messages = Object.keys(errors).map(function (key) { return errors[key]; });
                errorBox.textContent = messages.length > 0 ? messages.join(' ') : ERROR_GENERIC;
                errorBox.classList.remove('hidden');

                return;
            }

            // Alle Live-Suche-Widgets für Kunden auf der Seite mit dem neu
            // angelegten Kunden befüllen (siehe Kommentar oben). Über das
            // "livesearch:set"-Event statt direktem Setzen von
            // input.value/hidden.value, damit auch der intern im Widget
            // gemerkte "zuletzt bestätigte" Zustand aktualisiert wird (siehe
            // public/assets/js/live-search.js) — sonst würde das Feld beim
            // nächsten Verlassen wieder leer springen.
            var widgets = document.querySelectorAll('[data-live-search][data-url="/kunden/suche"]');
            widgets.forEach(function (widget) {
                widget.dispatchEvent(new CustomEvent('livesearch:set', {
                    detail: { id: result.data.id, label: result.data.displayName },
                }));
            });

            closeModal();
        }).catch(function () {
            submitButton.disabled = false;
            errorBox.textContent = ERROR_GENERIC;
            errorBox.classList.remove('hidden');
        });
    });
})();
</script>
