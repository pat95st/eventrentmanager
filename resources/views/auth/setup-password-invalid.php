<?php

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
<title>Link ungültig - Materialverleih Verwaltung</title>
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
      <h1 class="mb-1 text-lg font-semibold text-slate-900 dark:text-slate-100">Link ungültig</h1>
      <div class="mt-3 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-300">
        Dieser Link ist ungültig oder abgelaufen. Bitte einen Administrator um einen neuen Link bitten.
      </div>
      <a href="/login" class="mt-5 block text-center text-sm text-slate-500 hover:underline dark:text-slate-400">Zurück zur Anmeldung</a>
    </div>
  </div>
</body>
</html>
