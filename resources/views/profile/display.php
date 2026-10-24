<?php
/** @var int $defaultPageSize */
/** @var int[] $allowedPageSizes */
/** @var string $locale */
/** @var bool $notifyOnNewReceiptEmail */
/** @var bool $isAdministrator */
/** @var array<string, string> $errors */
/** @var ?string $vapidPublicKey */
/** @var \App\Domain\Push\PushSubscription[] $pushSubscriptions */
/** @var array<int, array{key: string, label: string, hint: ?string, swatch: string}> $colorSchemes */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('profile.display.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400"><?= e(t('profile.display.subtitle')) ?></p>
</div>

<div class="max-w-lg rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <?php if (isset($errors['pageSize'])): ?>
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-300">
            <?= e($errors['pageSize']) ?>
        </div>
    <?php endif; ?>
    <?php if (isset($errors['locale'])): ?>
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-300">
            <?= e($errors['locale']) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="/profil/darstellung">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

        <label for="default_page_size" class="block text-xs font-medium text-slate-600 dark:text-slate-400">
            <?= e(t('profile.display.page_size_label')) ?>
        </label>
        <p class="mb-2 mt-0.5 text-xs text-slate-400 dark:text-slate-500">
            <?= e(t('profile.display.page_size_hint')) ?>
        </p>
        <select id="default_page_size" name="default_page_size"
                class="mt-1 w-full max-w-[10rem] rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <?php foreach ($allowedPageSizes as $size): ?>
                <option value="<?= e((string) $size) ?>" <?= $defaultPageSize === $size ? 'selected' : '' ?>><?= e((string) $size) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="locale" class="mt-4 block text-xs font-medium text-slate-600 dark:text-slate-400">
            <?= e(t('profile.display.language_label')) ?>
        </label>
        <p class="mb-2 mt-0.5 text-xs text-slate-400 dark:text-slate-500">
            <?= e(t('profile.display.language_hint')) ?>
        </p>
        <select id="locale" name="locale"
                class="mt-1 w-full max-w-[10rem] rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
            <option value="de" <?= $locale === 'de' ? 'selected' : '' ?>><?= e(t('profile.display.language_de')) ?></option>
            <option value="en" <?= $locale === 'en' ? 'selected' : '' ?>><?= e(t('profile.display.language_en')) ?></option>
        </select>

        <?php if ($isAdministrator): ?>
        <div class="mt-4">
            <label class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-400">
                <input type="checkbox" name="notify_on_new_receipt_email" value="1" <?= $notifyOnNewReceiptEmail ? 'checked' : '' ?>
                       class="mt-0.5 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-700">
                <span><?= e(t('profile.display.notify_receipt_inbox_label')) ?></span>
            </label>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500"><?= e(t('profile.display.notify_receipt_inbox_hint')) ?></p>
        </div>
        <?php endif; ?>

        <div class="mt-4">
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
                <?= e(t('common.save')) ?>
            </button>
        </div>
    </form>
</div>

<div class="mt-6 max-w-lg rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <label for="theme_toggle_select" class="block text-xs font-medium text-slate-600 dark:text-slate-400">
        <?= e(t('profile.display.theme_label')) ?>
    </label>
    <p class="mb-2 mt-0.5 text-xs text-slate-400 dark:text-slate-500">
        <?= e(t('profile.display.theme_hint')) ?>
    </p>
    <select id="theme_toggle_select"
            class="mt-1 w-full max-w-[10rem] rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        <option value="auto"><?= e(t('profile.display.theme_auto')) ?></option>
        <option value="light"><?= e(t('profile.display.theme_light')) ?></option>
        <option value="dark"><?= e(t('profile.display.theme_dark')) ?></option>
    </select>
</div>

<?php
    // Farbschema (Akzentfarbe der Oberfläche): rein clientseitig in
    // localStorage gespeichert (kein DB-Feld), analog zum Hell-/Dunkelmodus
    // oben — siehe layout.php/auth/login.php/auth/two-factor.php für die
    // frühe Anwendung beim Seitenaufruf (vor dem ersten Rendern). Ehemals
    // eine eigene Seite (/einstellungen), jetzt hier bei den übrigen
    // Darstellungs-Einstellungen zusammengefasst.
?>
<div class="mt-6 max-w-lg rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
    <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.color_scheme.title')) ?></h2>
    <p class="mb-4 text-xs text-slate-400 dark:text-slate-500">
        <?= e(t('settings.color_scheme.hint')) ?>
    </p>

    <div id="scheme-picker" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <?php foreach ($colorSchemes as $scheme): ?>
            <button
                type="button"
                data-scheme-option="<?= e($scheme['key']) ?>"
                class="scheme-option flex items-center gap-3 rounded-md border border-slate-200 p-3 text-left hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
            >
                <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full ring-1 ring-inset ring-slate-200 dark:ring-slate-700" style="background-color: <?= e($scheme['swatch']) ?>;">
                    <svg data-scheme-check class="hidden h-4 w-4 text-white" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.415l-7.5 7.5a1 1 0 01-1.415 0l-3.5-3.5a1 1 0 111.415-1.414L8.5 12.086l6.79-6.794a1 1 0 011.415 0z" clip-rule="evenodd"/>
                    </svg>
                </span>
                <span>
                    <span class="block text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($scheme['label']) ?></span>
                    <?php if ($scheme['hint'] !== null): ?>
                        <span class="block text-[11px] text-slate-400 dark:text-slate-500"><?= e($scheme['hint']) ?></span>
                    <?php endif; ?>
                </span>
            </button>
        <?php endforeach; ?>

        <button
            type="button"
            data-scheme-option="custom"
            class="scheme-option flex items-center gap-3 rounded-md border border-slate-200 p-3 text-left hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800"
        >
            <span class="relative flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full ring-1 ring-inset ring-slate-200 dark:ring-slate-700">
                <input type="color" id="custom-color-input" value="#2563eb" class="h-8 w-8 cursor-pointer rounded-full border-0 bg-transparent p-0" title="<?= e(t('settings.color_scheme.custom_picker_label')) ?>">
                <svg data-scheme-check class="pointer-events-none absolute hidden h-4 w-4 text-white mix-blend-difference" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.415l-7.5 7.5a1 1 0 01-1.415 0l-3.5-3.5a1 1 0 111.415-1.414L8.5 12.086l6.79-6.794a1 1 0 011.415 0z" clip-rule="evenodd"/>
                </svg>
            </span>
            <span>
                <span class="block text-sm font-medium text-slate-800 dark:text-slate-200"><?= e(t('settings.color_scheme.custom')) ?></span>
                <span class="block text-[11px] text-slate-400 dark:text-slate-500"><?= e(t('settings.color_scheme.custom_hint')) ?></span>
            </span>
        </button>
    </div>
</div>

<div id="push-section" class="mt-6 max-w-lg rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900"
     data-vapid-public-key="<?= e($vapidPublicKey ?? '') ?>"
     data-label-enable="<?= e(t('profile.display.push.enable')) ?>"
     data-label-waiting="<?= e(t('profile.display.push.waiting')) ?>"
     data-error-generic="<?= e(t('profile.display.push.error_generic')) ?>"
     data-error-permission-denied="<?= e(t('profile.display.push.error_permission_denied')) ?>"
     data-confirm-remove="<?= e(t('profile.display.push.confirm_remove')) ?>"
     data-error-remove="<?= e(t('profile.display.push.error_remove')) ?>">
    <h2 class="mb-1 text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('profile.display.push.title')) ?></h2>
    <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">
        <?= e(t('profile.display.push.hint')) ?>
    </p>

    <div id="push-unsupported-note" class="hidden rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
        <?= e(t('profile.display.push.unsupported')) ?>
    </div>

    <div id="push-unconfigured-note" class="hidden rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
        <?= e(t('profile.display.push.unconfigured')) ?>
    </div>

    <div id="push-error" class="mb-4 hidden rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-300"></div>

    <ul id="push-subscription-list" class="mb-4 divide-y divide-slate-100 dark:divide-slate-800">
        <?php foreach ($pushSubscriptions as $subscription): ?>
            <li class="flex items-center justify-between py-3" data-push-subscription-id="<?= e($subscription->id) ?>">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-200"><?= e($subscription->userAgent ?? t('profile.display.push.unnamed_device')) ?></p>
                    <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('profile.display.push.registered_on', ['date' => $subscription->createdAt->format('d.m.Y H:i')])) ?></p>
                </div>
                <button type="button" class="push-remove-button flex-shrink-0 rounded-md border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                    <?= e(t('profile.display.push.remove')) ?>
                </button>
            </li>
        <?php endforeach; ?>
        <?php if ($pushSubscriptions === []): ?>
            <li id="push-subscription-empty-hint" class="py-3 text-sm text-slate-400 dark:text-slate-500"><?= e(t('profile.display.push.empty')) ?></li>
        <?php endif; ?>
    </ul>

    <button type="button" id="push-enable-button" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
        <?= e(t('profile.display.push.enable')) ?>
    </button>
</div>

<script>
(function () {
    function base64UrlToUint8Array(base64url) {
        var padded = base64url.replace(/-/g, '+').replace(/_/g, '/');
        while (padded.length % 4 !== 0) { padded += '='; }
        var binary = atob(padded);
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) { bytes[i] = binary.charCodeAt(i); }
        return bytes;
    }

    function bufferToBase64Url(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = '';
        for (var i = 0; i < bytes.length; i++) { binary += String.fromCharCode(bytes[i]); }
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    var section = document.getElementById('push-section');
    var vapidPublicKey = section.getAttribute('data-vapid-public-key');
    var enableButton = document.getElementById('push-enable-button');
    var errorBox = document.getElementById('push-error');
    var list = document.getElementById('push-subscription-list');
    var emptyHint = document.getElementById('push-subscription-empty-hint');
    var labelEnable = section.getAttribute('data-label-enable');
    var labelWaiting = section.getAttribute('data-label-waiting');
    var errorGeneric = section.getAttribute('data-error-generic');
    var errorPermissionDenied = section.getAttribute('data-error-permission-denied');
    var confirmRemove = section.getAttribute('data-confirm-remove');
    var errorRemove = section.getAttribute('data-error-remove');

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
    }

    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !window.Notification) {
        enableButton.classList.add('hidden');
        document.getElementById('push-unsupported-note').classList.remove('hidden');
        return;
    }

    if (!vapidPublicKey) {
        // Noch kein VAPID-Schlüsselpaar erzeugt (Administrator muss zuerst
        // /einstellungen/push aufrufen) — Abonnieren ist erst danach möglich.
        enableButton.classList.add('hidden');
        document.getElementById('push-unconfigured-note').classList.remove('hidden');
        return;
    }

    enableButton.addEventListener('click', function () {
        errorBox.classList.add('hidden');
        enableButton.disabled = true;
        enableButton.textContent = labelWaiting;

        navigator.serviceWorker.register('/sw.js')
            .then(function (registration) {
                return registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: base64UrlToUint8Array(vapidPublicKey),
                });
            })
            .then(function (subscription) {
                var keys = subscription.toJSON().keys;

                return fetch('/profil/push-abo', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-Token': window.CSRF_TOKEN,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({
                        endpoint: subscription.endpoint,
                        p256dh: keys.p256dh,
                        auth: keys.auth,
                        userAgent: navigator.userAgent,
                    }),
                });
            })
            .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
            .then(function (result) {
                if (!result.ok || !result.body.success) {
                    throw new Error((result.body && result.body.error) || errorGeneric);
                }
                window.location.reload();
            })
            .catch(function (err) {
                var isPermissionDenied = window.Notification && Notification.permission === 'denied';
                showError(isPermissionDenied ? errorPermissionDenied : ((err && err.message) || errorGeneric));
                enableButton.disabled = false;
                enableButton.textContent = labelEnable;
            });
    });

    list.addEventListener('click', async function (event) {
        var button = event.target.closest('.push-remove-button');
        if (!button) { return; }
        if (!event.shiftKey) {
            var confirmed = await window.confirmModal(confirmRemove);
            if (!confirmed) { return; }
        }

        var item = button.closest('[data-push-subscription-id]');
        var id = item.getAttribute('data-push-subscription-id');

        fetch('/profil/push-abo/' + encodeURIComponent(id) + '/loeschen', {
            method: 'POST',
            headers: { 'X-CSRF-Token': window.CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (res) { return res.json(); })
            .then(function (body) {
                if (!body.success) {
                    throw new Error(body.error || errorRemove);
                }
                item.remove();
                if (!list.querySelector('[data-push-subscription-id]') && emptyHint) {
                    emptyHint.classList.remove('hidden');
                }
            })
            .catch(function (err) {
                showError(err && err.message ? err.message : errorRemove);
            });
    });
})();
</script>

<script>
  // Hell-/Dunkelmodus/Automatisch-Auswahl: dieselbe localStorage-basierte
  // Mechanik wie im <head>-Skript jeder Seite (siehe z. B.
  // resources/views/layout.php für das initiale Setzen beim Laden).
  // "auto" (bzw. kein gespeicherter Wert) folgt der Betriebssystem-
  // Einstellung über prefers-color-scheme und reagiert auch live auf deren
  // Änderung, solange diese Auswahl aktiv ist. Kein Server-Zustand, rein
  // clientseitig je Browser.
  (function () {
    var select = document.getElementById('theme_toggle_select');
    var root = document.documentElement;
    var media = window.matchMedia('(prefers-color-scheme: dark)');

    function currentMode() {
      try {
        var stored = localStorage.getItem('theme');

        return stored === 'dark' || stored === 'light' ? stored : 'auto';
      } catch (e) {
        return 'auto';
      }
    }

    function applyMode(mode) {
      var isDark = mode === 'dark' || (mode === 'auto' && media.matches);
      root.classList.toggle('dark', isDark);
    }

    select.value = currentMode();

    select.addEventListener('change', function () {
        var mode = select.value;
        applyMode(mode);
        try {
          localStorage.setItem('theme', mode);
        } catch (e) {}
    });

    // Live nachziehen, wenn die Betriebssystem-Einstellung sich ändert,
    // solange "Automatisch" gewählt ist.
    media.addEventListener('change', function () {
      if (currentMode() === 'auto') {
        applyMode('auto');
      }
    });
  })();
</script>

<script>
  // Farbschema-Auswahl (Presets + eigene Farbe per Colorpicker): dieselbe
  // localStorage-Mechanik wie der Hell-/Dunkelmodus oben, siehe auch
  // layout.php/auth/login.php/auth/two-factor.php für die identische
  // HSL-Rampen-Berechnung beim initialen Seitenaufruf (dort bewusst
  // dupliziert, da diese Seite layout.php nicht einbindet) — hier zusätzlich
  // interaktiv für die sofortige Live-Vorschau beim Ändern.
  (function () {
    var root = document.documentElement;
    var buttons = document.querySelectorAll('[data-scheme-option]');
    var customInput = document.getElementById('custom-color-input');
    var rampSteps = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'];
    var ramp = { 50: 0.97, 100: 0.93, 200: 0.87, 300: 0.78, 400: 0.68, 500: 0.60, 600: 0.53, 700: 0.48, 800: 0.40, 900: 0.33, 950: 0.21 };

    function hexToHs(hex) {
      var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
      if (!m) { return null; }
      var r = parseInt(m[1], 16) / 255, g = parseInt(m[2], 16) / 255, b = parseInt(m[3], 16) / 255;
      var max = Math.max(r, g, b), min = Math.min(r, g, b), h = 0, s = 0, l = (max + min) / 2;
      if (max !== min) {
        var d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        if (max === r) { h = (g - b) / d + (g < b ? 6 : 0); }
        else if (max === g) { h = (b - r) / d + 2; }
        else { h = (r - g) / d + 4; }
        h /= 6;
      }
      return { h: h, s: s };
    }

    function hslToRgb(h, s, l) {
      function hue2rgb(p, q, t) {
        if (t < 0) { t += 1; }
        if (t > 1) { t -= 1; }
        if (t < 1 / 6) { return p + (q - p) * 6 * t; }
        if (t < 1 / 2) { return q; }
        if (t < 2 / 3) { return p + (q - p) * (2 / 3 - t) * 6; }
        return p;
      }
      var r, g, b;
      if (s === 0) {
        r = g = b = l;
      } else {
        var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        var p = 2 * l - q;
        r = hue2rgb(p, q, h + 1 / 3);
        g = hue2rgb(p, q, h);
        b = hue2rgb(p, q, h - 1 / 3);
      }
      return { r: Math.round(r * 255), g: Math.round(g * 255), b: Math.round(b * 255) };
    }

    function clearCustomVars() {
      rampSteps.forEach(function (step) {
        root.style.removeProperty('--color-brand-' + step);
      });
    }

    function applyCustomColor(hex) {
      var hs = hexToHs(hex);
      if (!hs) { return; }
      rampSteps.forEach(function (step) {
        var rgb = hslToRgb(hs.h, hs.s, ramp[step]);
        root.style.setProperty('--color-brand-' + step, rgb.r + ' ' + rgb.g + ' ' + rgb.b);
      });
    }

    function currentScheme() {
      return root.getAttribute('data-scheme') || 'blue';
    }

    function syncSelection() {
      var active = currentScheme();
      buttons.forEach(function (button) {
        var isActive = button.getAttribute('data-scheme-option') === active;
        button.classList.toggle('ring-2', isActive);
        button.classList.toggle('ring-brand-500', isActive);
        button.classList.toggle('border-brand-300', isActive);
        var check = button.querySelector('[data-scheme-check]');
        if (check) {
          check.classList.toggle('hidden', !isActive);
        }
      });
    }

    try {
      var storedCustomHex = localStorage.getItem('customColorHex');
      if (storedCustomHex && customInput) {
        customInput.value = storedCustomHex;
      }
    } catch (e) {}

    buttons.forEach(function (button) {
      var scheme = button.getAttribute('data-scheme-option');
      if (scheme === 'custom') {
        return;
      }
      button.addEventListener('click', function () {
        clearCustomVars();
        root.setAttribute('data-scheme', scheme);
        try {
          localStorage.setItem('colorScheme', scheme);
        } catch (e) {}
        syncSelection();
      });
    });

    if (customInput) {
      customInput.addEventListener('input', function () {
        var hex = customInput.value;
        applyCustomColor(hex);
        root.setAttribute('data-scheme', 'custom');
        try {
          localStorage.setItem('colorScheme', 'custom');
          localStorage.setItem('customColorHex', hex);
        } catch (e) {}
        syncSelection();
      });
    }

    syncSelection();
  })();
</script>
