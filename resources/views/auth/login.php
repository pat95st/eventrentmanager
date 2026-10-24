<?php
/** @var array<string, string> $errors */
/** @var string $email */

use App\Infrastructure\Http\CompanyBranding;
use App\Infrastructure\Http\Locale;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$activeLocale = Locale::get();
$companyLogoUrl = CompanyBranding::logoUrl();
$companyDarkLogoUrl = CompanyBranding::darkLogoUrl();
$companyLogoMaxHeightPx = CompanyBranding::logoMaxHeightPx();
?>
<!DOCTYPE html>
<html lang="<?= e($activeLocale) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('auth.login.title')) ?> - Materialverleih Verwaltung</title>
<link rel="stylesheet" href="/assets/css/app.css">
<?php require __DIR__ . '/../_pwa_head.php'; ?>
<script>
  (function () {
    try {
      var stored = localStorage.getItem('theme');
      var dark = stored === 'dark' || ((stored === null || stored === 'auto') && window.matchMedia('(prefers-color-scheme: dark)').matches);
      document.documentElement.classList.toggle('dark', dark);

      var storedScheme = localStorage.getItem('colorScheme');
      if (storedScheme === 'custom') {
        var hex = localStorage.getItem('customColorHex');
        if (hex) { applyCustomBrandColor(hex); }
      } else if (storedScheme) {
        document.documentElement.setAttribute('data-scheme', storedScheme);
      }
    } catch (e) {}

    // Siehe layout.php für die identische Begründung dieses Musters
    // (bewusst dupliziert, da diese Seite layout.php nicht einbindet).
    function applyCustomBrandColor(hex) {
      var m = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
      if (!m) { return; }
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
      var ramp = { 50: 0.97, 100: 0.93, 200: 0.87, 300: 0.78, 400: 0.68, 500: 0.60, 600: 0.53, 700: 0.48, 800: 0.40, 900: 0.33, 950: 0.21 };
      var root = document.documentElement;
      Object.keys(ramp).forEach(function (step) {
        var rgb = hslToRgb(h, s, ramp[step]);
        root.style.setProperty('--color-brand-' + step, rgb.r + ' ' + rgb.g + ' ' + rgb.b);
      });
      root.setAttribute('data-scheme', 'custom');
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
  })();
</script>
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 antialiased dark:bg-slate-950">
  <div class="w-full max-w-sm px-4">
    <div class="mb-6 flex justify-center">
      <?php if ($companyLogoUrl !== null): ?>
        <img
          src="<?= e($companyLogoUrl) ?>"
          alt="Logo"
          style="max-height: <?= (int) $companyLogoMaxHeightPx ?>px;"
          class="<?= $companyDarkLogoUrl !== null ? 'dark:hidden ' : '' ?>w-auto max-w-[12rem] rounded object-contain"
        >
        <?php if ($companyDarkLogoUrl !== null): ?>
        <img
          src="<?= e($companyDarkLogoUrl) ?>"
          alt="Logo"
          style="max-height: <?= (int) $companyLogoMaxHeightPx ?>px;"
          class="hidden w-auto max-w-[12rem] rounded object-contain dark:block"
        >
        <?php endif; ?>
      <?php else: ?>
        <span class="rounded bg-brand-600 px-3 py-1.5 font-mono text-sm text-white">M01</span>
      <?php endif; ?>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
      <h1 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100"><?= e(t('auth.login.heading')) ?></h1>
      <p class="mb-5 text-sm text-slate-500 dark:text-slate-400"><?= e(t('auth.login.subheading')) ?></p>

      <?php if (isset($errors['credentials'])): ?>
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-300">
          <?= e($errors['credentials']) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="/login" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <div>
          <label for="email" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('auth.login.email_label')) ?></label>
          <input type="email" id="email" name="email" required autofocus value="<?= e($email) ?>"
                 class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <div>
          <label for="password" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('auth.login.password_label')) ?></label>
          <input type="password" id="password" name="password" required
                 class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <button type="submit" class="w-full rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
          <?= e(t('auth.login.submit')) ?>
        </button>
      </form>

      <a href="/passwort-vergessen" class="mt-3 block text-center text-sm text-slate-500 hover:underline dark:text-slate-400">Passwort vergessen?</a>

      <div id="passkey-login-block" class="mt-4 hidden">
        <div class="my-4 flex items-center gap-3 text-xs text-slate-400 dark:text-slate-500">
          <span class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></span>
          <?= e(t('auth.login.or')) ?>
          <span class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></span>
        </div>

        <div id="passkey-login-error" class="mb-3 hidden rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-300"></div>

        <button type="button" id="passkey-login-button" data-label="<?= e(t('auth.login.passkey_button')) ?>" data-waiting-label="<?= e(t('auth.login.passkey_waiting')) ?>" data-error="<?= e(t('auth.login.passkey_error')) ?>" class="w-full rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
          <?= e(t('auth.login.passkey_button')) ?>
        </button>
      </div>

      <!-- Sprachumschalter, auch vor der Anmeldung nutzbar (siehe
           App\Infrastructure\Http\Controllers\LocaleController). -->
      <div class="mt-5 flex items-center justify-center gap-1 text-xs font-medium text-slate-400">
        <form method="post" action="/sprache">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
          <input type="hidden" name="locale" value="de">
          <input type="hidden" name="redirect" value="/login">
          <button type="submit" class="rounded px-1 hover:text-slate-700 dark:hover:text-slate-200 <?= $activeLocale === 'de' ? 'text-brand-700 dark:text-brand-400' : '' ?>"><?= e(t('nav.language_de')) ?></button>
        </form>
        <span>|</span>
        <form method="post" action="/sprache">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
          <input type="hidden" name="locale" value="en">
          <input type="hidden" name="redirect" value="/login">
          <button type="submit" class="rounded px-1 hover:text-slate-700 dark:hover:text-slate-200 <?= $activeLocale === 'en' ? 'text-brand-700 dark:text-brand-400' : '' ?>"><?= e(t('nav.language_en')) ?></button>
        </form>
      </div>
    </div>
  </div>

<script>
(function () {
  // base64url (RFC 4648 §5) <-> ArrayBuffer — WebAuthn überträgt alle
  // Binärfelder (Challenge, Credential-ID, …) so kodiert.
  function base64UrlToBuffer(base64url) {
    var padded = base64url.replace(/-/g, '+').replace(/_/g, '/');
    while (padded.length % 4 !== 0) { padded += '='; }
    var binary = atob(padded);
    var bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) { bytes[i] = binary.charCodeAt(i); }
    return bytes.buffer;
  }

  function bufferToBase64Url(buffer) {
    var bytes = new Uint8Array(buffer);
    var binary = '';
    for (var i = 0; i < bytes.length; i++) { binary += String.fromCharCode(bytes[i]); }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  var block = document.getElementById('passkey-login-block');
  var button = document.getElementById('passkey-login-button');
  var errorBox = document.getElementById('passkey-login-error');

  // Feature-Detection: Passkey-Button nur anzeigen, wenn der Browser die
  // WebAuthn-API überhaupt unterstützt (native Browser-API, kein Polyfill).
  if (!window.PublicKeyCredential) {
    return;
  }
  block.classList.remove('hidden');

  function showError(message) {
    errorBox.textContent = message;
    errorBox.classList.remove('hidden');
  }

  var buttonLabel = button.getAttribute('data-label');
  var waitingLabel = button.getAttribute('data-waiting-label');
  var defaultErrorMessage = button.getAttribute('data-error');

  button.addEventListener('click', function () {
    errorBox.classList.add('hidden');
    button.disabled = true;
    button.textContent = waitingLabel;

    fetch('/passkeys/login/options', { method: 'POST' })
      .then(function (res) { return res.json(); })
      .then(function (body) {
        var options = body.options;
        var publicKey = {
          challenge: base64UrlToBuffer(options.challenge),
          rpId: options.rpId,
          timeout: options.timeout,
          userVerification: options.userVerification,
          allowCredentials: (options.allowCredentials || []).map(function (c) {
            return { type: c.type, id: base64UrlToBuffer(c.id) };
          }),
        };

        return navigator.credentials.get({ publicKey: publicKey }).then(function (assertion) {
          return fetch('/passkeys/login/complete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
              challengeId: body.challengeId,
              credentialId: bufferToBase64Url(assertion.rawId),
              clientDataJSON: bufferToBase64Url(assertion.response.clientDataJSON),
              authenticatorData: bufferToBase64Url(assertion.response.authenticatorData),
              signature: bufferToBase64Url(assertion.response.signature),
            }),
          });
        });
      })
      .then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
      .then(function (result) {
        if (!result.ok || !result.body.success) {
          throw new Error((result.body && result.body.error) || defaultErrorMessage);
        }
        window.location.href = result.body.redirect || '/';
      })
      .catch(function (err) {
        showError(err && err.message ? err.message : defaultErrorMessage);
        button.disabled = false;
        button.textContent = buttonLabel;
      });
  });
})();
</script>
</body>
</html>
