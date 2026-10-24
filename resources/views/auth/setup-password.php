<?php
/** @var string $token */
/** @var array<string, string> $errors */

use App\Infrastructure\Http\CompanyBranding;
use App\Infrastructure\Http\Locale;
use function App\Infrastructure\Http\e;

$companyLogoUrl = CompanyBranding::logoUrl();
$companyDarkLogoUrl = CompanyBranding::darkLogoUrl();
$companyLogoMaxHeightPx = CompanyBranding::logoMaxHeightPx();
?>
<!DOCTYPE html>
<html lang="<?= e(Locale::get()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Passwort festlegen - Materialverleih Verwaltung</title>
<link rel="stylesheet" href="/assets/css/app.css">
<?php require __DIR__ . '/../_pwa_head.php'; ?>
<script>
  (function () {
    try {
      var stored = localStorage.getItem('theme');
      var dark = stored === 'dark' || ((stored === null || stored === 'auto') && window.matchMedia('(prefers-color-scheme: dark)').matches);
      document.documentElement.classList.toggle('dark', dark);
    } catch (e) {}
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
      <h1 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Passwort festlegen</h1>
      <p class="mb-5 text-sm text-slate-500 dark:text-slate-400">Bitte wählen Sie ein neues Passwort für Ihr Konto.</p>

      <?php if (isset($errors['password'])): ?>
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-300">
          <?= e($errors['password']) ?>
        </div>
      <?php endif; ?>
      <?php if (isset($errors['password_confirmation'])): ?>
        <div class="mb-4 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-300">
          <?= e($errors['password_confirmation']) ?>
        </div>
      <?php endif; ?>

      <form method="post" action="/passwort-einrichten/<?= e($token) ?>" class="space-y-4">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <div>
          <label for="password" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Neues Passwort</label>
          <input type="password" id="password" name="password" required autofocus
                 class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
          <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Mindestens 10 Zeichen.</p>
        </div>
        <div>
          <label for="password_confirmation" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Passwort bestätigen</label>
          <input type="password" id="password_confirmation" name="password_confirmation" required
                 class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
        </div>
        <button type="submit" class="w-full rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
          Passwort festlegen
        </button>
      </form>
    </div>
  </div>
</body>
</html>
