<?php
/** @var \App\Domain\User\TwoFactorMethod $method */
/** @var array<string, string> $errors */
/** @var string|null $flashSuccess */

use App\Domain\User\TwoFactorMethod;
use App\Infrastructure\Http\CompanyBranding;
use App\Infrastructure\Http\Locale;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$companyLogoUrl = CompanyBranding::logoUrl();
$companyDarkLogoUrl = CompanyBranding::darkLogoUrl();
$companyLogoMaxHeightPx = CompanyBranding::logoMaxHeightPx();
?>
<!DOCTYPE html>
<html lang="<?= e(Locale::get()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(t('auth.two_factor.title')) ?> - Materialverleih Verwaltung</title>
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
      <h1 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100"><?= e(t('auth.two_factor.heading')) ?></h1>
      <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">
        <?php if ($method === TwoFactorMethod::Totp): ?>
          <?= e(t('auth.two_factor.totp_hint')) ?>
        <?php else: ?>
          <?= e(t('auth.two_factor.email_hint')) ?>
        <?php endif; ?>
      </p>

      <?php if (($flashSuccess ?? null) !== null): ?>
        <div class="mb-4 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
          <?= e($flashSuccess) ?>
        </div>
      <?php endif; ?>

      <?php if (isset($errors['code'])): ?>
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-300">
          <?= e($errors['code']) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="/login/zwei-faktor" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <div>
          <label for="code" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('auth.two_factor.code_label')) ?></label>
          <input type="text" id="code" name="code" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus
                 class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-center font-mono text-lg tracking-[0.3em] focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <button type="submit" class="w-full rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
          <?= e(t('auth.two_factor.submit')) ?>
        </button>
      </form>

      <?php if ($method === TwoFactorMethod::Email): ?>
        <form method="post" action="/login/zwei-faktor/code-erneut-senden" class="mt-3">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
          <button type="submit" class="w-full text-center text-sm text-slate-500 hover:underline dark:text-slate-400">
            <?= e(t('auth.two_factor.resend')) ?>
          </button>
        </form>
      <?php endif; ?>

      <form method="post" action="/logout" class="mt-1">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <button type="submit" class="w-full text-center text-sm text-slate-500 hover:underline dark:text-slate-400">
          <?= e(t('common.cancel')) ?>
        </button>
      </form>
    </div>
  </div>
</body>
</html>
