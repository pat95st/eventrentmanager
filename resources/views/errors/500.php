<?php
/**
 * Generische Fehlerseite für unerwartete/nicht behandelte Fehler (siehe
 * public/index.php, das jeden nicht abgefangenen Throwable hierher
 * umleitet, statt eine leere weiße Seite auszuliefern). Absichtlich OHNE
 * technische Details (Stacktrace, Exception-Klasse, SQL etc.) — diese
 * landen stattdessen im PHP-Error-Log (error_log), damit sie nicht
 * versehentlich Interna preisgeben, aber trotzdem auffindbar bleiben.
 */
use function App\Infrastructure\Http\e;
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ein Fehler ist aufgetreten</title>
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
  <div class="max-w-md text-center px-4">
    <p class="text-sm font-medium uppercase tracking-wide text-slate-400 dark:text-slate-500">Fehler 500</p>
    <h1 class="mt-2 text-xl font-semibold text-slate-900 dark:text-slate-100">Ein Fehler ist aufgetreten</h1>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
      Es ist ein unerwarteter Fehler aufgetreten. Bitte versuche es erneut oder wende dich an einen Administrator,
      falls das Problem bestehen bleibt (Details wurden im Server-Log erfasst).
    </p>
    <a href="/artikel" class="mt-6 inline-block rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
      Zur Artikelübersicht
    </a>
  </div>
</body>
</html>
