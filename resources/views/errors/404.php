<?php
/** @var string $message */
use function App\Infrastructure\Http\e;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Nicht gefunden</title>
<link rel="stylesheet" href="/assets/css/app.css">
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
<body class="flex min-h-screen items-center justify-center antialiased">
  <div class="max-w-md text-center">
    <p class="text-sm font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Fehler 404</p>
    <h1 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Nicht gefunden</h1>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400"><?= e($message) ?></p>
    <a href="/artikel" class="mt-6 inline-block rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
      Zur Artikelübersicht
    </a>
  </div>
</body>
</html>
