<?php
/** @var string $content */
/** @var string $activeNav */
/** @var string $title */
/** @var string|null $flashSuccess */
/** @var string|null $flashError */
/** @var \App\Domain\User\User|null $currentUser */
/** @var \App\Domain\User\User|null $impersonator */
/** @var \App\Domain\Settings\LegalSettings|null $legalSettings */

// Footer-Links Impressum/Datenschutz: im Link-Modus (mit hinterlegter URL)
// geht es direkt auf die externe Seite (neuer Tab), sonst auf die eigene,
// öffentliche Markdown-Seite (siehe LegalPageController). Die Links werden
// bewusst auch ohne hinterlegten Inhalt gezeigt (siehe Aufgabenstellung),
// damit rechtlich relevante Seiten immer auffindbar bleiben.
$imprintHref = '/impressum';
$imprintExternal = false;
if ($legalSettings !== null && $legalSettings->imprintMode === 'link' && ($legalSettings->imprintUrl ?? '') !== '') {
    $imprintHref = $legalSettings->imprintUrl;
    $imprintExternal = true;
}
$privacyHref = '/datenschutz';
$privacyExternal = false;
if ($legalSettings !== null && $legalSettings->privacyMode === 'link' && ($legalSettings->privacyUrl ?? '') !== '') {
    $privacyHref = $legalSettings->privacyUrl;
    $privacyExternal = true;
}

// Einheitliches Schema für den Seitentitel: "(Funktionalität) - Materialverleih
// Verwaltung". Ohne übergebenen Titel (z. B. Fehlerseiten) bleibt es beim
// reinen Suffix.
$pageTitle = $title !== '' ? $title . ' - Materialverleih Verwaltung' : 'Materialverleih Verwaltung';

use App\Domain\User\Permission;
use App\Infrastructure\Http\CompanyBranding;
use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\Locale;
use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$companyLogoUrl = CompanyBranding::logoUrl();
$companyDarkLogoUrl = CompanyBranding::darkLogoUrl();
$companyLogoMaxHeightPx = CompanyBranding::logoMaxHeightPx();
$companyLogoAlt = CompanyBranding::brandName() ?? t('nav.brand');
$backgroundImageUrl = CompanyBranding::backgroundImageUrl();

// "Artikel" ist ein Menüpunkt mit Untermenü (Hersteller/Kategorien sind
// dem Artikelstamm untergeordnet); RMA ist ein eigener, gleichrangiger
// Menüpunkt. Jeder Menüpunkt wird nur angezeigt, wenn der angemeldete
// Benutzer das zugehörige Recht besitzt (siehe Zuordnung in bootstrap.php).
$articlesGroupKeys = ['articles', 'cases', 'manufacturers', 'arbeitszeit', 'categories', 'rma', 'firmware'];
$articlesChildren = [
    ['key' => 'articles', 'label' => t('nav.articles_all'), 'href' => '/artikel'],
    ['key' => 'cases', 'label' => t('nav.cases'), 'href' => '/cases'],
    ['key' => 'manufacturers', 'label' => t('nav.manufacturers'), 'href' => '/hersteller'],
    ['key' => 'arbeitszeit', 'label' => t('nav.labor_time_articles'), 'href' => '/arbeitszeit-artikel'],
    ['key' => 'categories', 'label' => t('nav.categories'), 'href' => '/kategorien'],
    ['key' => 'rma', 'label' => t('nav.rma'), 'href' => '/rma'],
    ['key' => 'firmware', 'label' => t('nav.firmware'), 'href' => '/firmware'],
];
$isArticlesGroupActive = in_array($activeNav, $articlesGroupKeys, true);

// "Veranstaltung" ist analog zu "Artikel" ein Menüpunkt mit Untermenü
// (Veranstaltungsorte und Kalender sind der Veranstaltungsverwaltung
// untergeordnet).
$rentalsGroupKeys = ['rentals', 'offers', 'invoices', 'venues', 'warehouses', 'calendar'];
$rentalsChildren = [
    ['key' => 'rentals', 'label' => t('nav.rentals_all'), 'href' => '/veranstaltungen'],
    ['key' => 'offers', 'label' => t('nav.offers'), 'href' => '/angebote'],
    ['key' => 'invoices', 'label' => t('nav.invoices'), 'href' => '/rechnungen'],
    ['key' => 'venues', 'label' => t('nav.venues'), 'href' => '/veranstaltungsorte'],
    ['key' => 'warehouses', 'label' => t('nav.warehouses'), 'href' => '/lagerorte'],
    ['key' => 'calendar', 'label' => t('nav.calendar'), 'href' => '/kalender'],
];
$isRentalsGroupActive = in_array($activeNav, $rentalsGroupKeys, true);

// Sprache für das <html lang="..."> Attribut (siehe App\Infrastructure\Http\
// Locale) — der Umschalter selbst liegt in den Benutzereinstellungen
// (resources/views/profile/display.php), nicht mehr hier im Header.
$activeLocale = Locale::get();

$canArticles = $currentUser?->has(Permission::Artikelverwaltung) ?? false;
$canCustomers = $currentUser?->has(Permission::Kundenverwaltung) ?? false;
$canRentals = $currentUser?->has(Permission::AusgabeRuecknahme) ?? false;
$isAdmin = $currentUser?->has(Permission::Administration) ?? false;
?>
<!DOCTYPE html>
<html lang="<?= e($activeLocale) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<!--
  Lokal gebautes Tailwind-CSS (kein CDN-Skript, keine Laufzeit-Abhängigkeit
  von externen Servern). Erzeugt aus resources/css/app.css per Tailwind-CLI,
  siehe package.json ("npm run build:css") und README.
-->
<link rel="stylesheet" href="/assets/css/app.css">
<?php require __DIR__ . '/_pwa_head.php'; ?>
<script>
  // CSRF-Token einmal pro Seitenaufruf global verfügbar machen, für alle
  // per fetch() ausgelösten, zustandsändernden Aktionen (siehe Router::
  // enforceCsrf() für die serverseitige Prüfung und resources/views/
  // profile/security.php bzw. auth/login.php für die Verwendung).
  window.CSRF_TOKEN = <?= json_encode(Csrf::token(), JSON_THROW_ON_ERROR) ?>;
</script>
<script>
  // Theme und Farbschema so früh wie möglich setzen (vor dem ersten
  // Rendern), damit die Seite nicht kurz im falschen Modus/Farbschema
  // aufblitzt. Gespeicherte Wahl hat Vorrang, sonst gilt die
  // Systemeinstellung (Hell/Dunkel) bzw. das Standard-Farbschema "blue"
  // (siehe Benutzereinstellungen -> Darstellung, resources/views/profile/
  // display.php, und public/assets/css/app.css, Abschnitt "Farbschema").
  // Bei "custom" (frei per Colorpicker gewählte Farbe) werden die
  // --color-brand-*-CSS-Variablen HIER direkt aus dem gespeicherten Hex-Wert
  // berechnet (HSL-Rampe, siehe applyCustomBrandColor()) statt über eines
  // der data-scheme-Presets in app.css — bewusst dupliziert in
  // auth/login.php und auth/two-factor.php (siehe dort), da diese Seiten
  // layout.php nicht einbinden.
  (function () {
    try {
      var storedTheme = localStorage.getItem('theme');
      var dark = storedTheme === 'dark' || ((storedTheme === null || storedTheme === 'auto') && window.matchMedia('(prefers-color-scheme: dark)').matches);
      document.documentElement.classList.toggle('dark', dark);

      var storedScheme = localStorage.getItem('colorScheme');
      if (storedScheme === 'custom') {
        var hex = localStorage.getItem('customColorHex');
        if (hex) { applyCustomBrandColor(hex); }
      } else if (storedScheme) {
        document.documentElement.setAttribute('data-scheme', storedScheme);
      }
    } catch (e) {}

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
<body
  class="flex min-h-screen flex-col antialiased<?= $backgroundImageUrl !== null ? ' has-bg-image' : '' ?>"
  <?php if ($backgroundImageUrl !== null): ?>style="--app-bg-image: url('<?= e($backgroundImageUrl) ?>');"<?php endif; ?>
>
  <!--
    Globales Bestätigungs-Modal: ersetzt window.confirm() im gesamten
    Frontend (siehe window.confirmModal()-Helfer weiter unten). Formulare
    markieren sich mit data-confirm="Frage..." (optional data-confirm-
    style="danger" für den roten Bestätigen-Button) statt eines
    onsubmit="return confirm(...)"-Attributs.
  -->
  <dialog id="global-confirm-dialog" class="w-full max-w-md rounded-lg border border-slate-200 p-0 backdrop:bg-black/40 dark:border-slate-700 dark:bg-slate-900">
    <div class="p-4">
      <p id="global-confirm-message" class="mb-4 text-sm text-slate-700 dark:text-slate-300"></p>
      <div class="flex justify-end gap-2">
        <button type="button" id="global-confirm-cancel" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Abbrechen</button>
        <button type="button" id="global-confirm-ok" class="rounded-md px-3 py-1.5 text-sm font-medium text-white">Bestätigen</button>
      </div>
    </div>
  </dialog>
  <header class="border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
    <div class="flex items-center gap-3 px-4 py-3 lg:gap-8">
      <button
        type="button"
        id="mobile-menu-toggle"
        aria-label="<?= e(t('nav.menu_open')) ?>"
        aria-expanded="false"
        aria-controls="mobile-nav"
        class="rounded-md border border-slate-300 p-2 text-slate-500 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 lg:hidden"
      >
        <svg id="mobile-menu-icon-open" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 4.75zm0 5A.75.75 0 012.75 9h14.5a.75.75 0 010 1.5H2.75A.75.75 0 012 9.75zM2.75 14a.75.75 0 000 1.5h14.5a.75.75 0 000-1.5H2.75z" clip-rule="evenodd"/>
        </svg>
        <svg id="mobile-menu-icon-close" class="hidden h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
          <path fill-rule="evenodd" d="M5.28 4.22a.75.75 0 00-1.06 1.06L8.94 10l-4.72 4.72a.75.75 0 101.06 1.06L10 11.06l4.72 4.72a.75.75 0 101.06-1.06L11.06 10l4.72-4.72a.75.75 0 00-1.06-1.06L10 8.94 5.28 4.22z" clip-rule="evenodd"/>
        </svg>
      </button>

      <a href="/artikel" class="flex items-center gap-2 font-semibold text-slate-900 dark:text-slate-100">
        <?php if ($companyLogoUrl !== null): ?>
          <img
            src="<?= e($companyLogoUrl) ?>"
            alt="<?= e($companyLogoAlt) ?>"
            style="max-height: <?= (int) $companyLogoMaxHeightPx ?>px;"
            class="<?= $companyDarkLogoUrl !== null ? 'dark:hidden ' : '' ?>w-auto max-w-[7rem] rounded object-contain"
          >
          <?php if ($companyDarkLogoUrl !== null): ?>
          <img
            src="<?= e($companyDarkLogoUrl) ?>"
            alt="<?= e($companyLogoAlt) ?>"
            style="max-height: <?= (int) $companyLogoMaxHeightPx ?>px;"
            class="hidden w-auto max-w-[7rem] rounded object-contain dark:block"
          >
          <?php endif; ?>
        <?php else: ?>
          <span class="rounded bg-brand-600 px-2 py-1 font-mono text-xs text-white">M01</span>
        <?php endif; ?>
      </a>

      <!-- Desktop-Navigation: ab lg (1024px) sichtbar, siehe mobile-nav weiter
           unten für die schmale Variante darunter. -->
      <nav class="hidden flex-1 gap-1 lg:flex">
        <?php if ($canArticles): ?>
        <div class="group relative">
          <a
            href="/artikel"
            class="flex items-center gap-1 rounded-md px-3 py-1.5 text-sm font-medium <?= $isArticlesGroupActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
          >
            <?= e(t('nav.articles')) ?>
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
          </a>
          <div class="invisible absolute left-0 top-full z-10 w-44 rounded-md border border-slate-200 bg-white py-1 opacity-0 shadow-lg transition-opacity duration-100 group-hover:visible group-hover:opacity-100 dark:border-slate-800 dark:bg-slate-900">
            <?php foreach ($articlesChildren as $child): ?>
              <a
                href="<?= e($child['href']) ?>"
                class="block px-3 py-2 text-sm <?= $activeNav === $child['key'] ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
              ><?= e($child['label']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($canCustomers): ?>
        <a
          href="/kunden"
          class="rounded-md px-3 py-1.5 text-sm font-medium <?= $activeNav === 'customers' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
        ><?= e(t('nav.customers')) ?></a>
        <?php endif; ?>

        <?php if ($canArticles): ?>
        <a
          href="/lieferanten"
          class="rounded-md px-3 py-1.5 text-sm font-medium <?= $activeNav === 'suppliers' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
        ><?= e(t('nav.suppliers')) ?></a>
        <?php endif; ?>

        <?php if ($canArticles): ?>
        <a
          href="/belege"
          class="relative rounded-md px-3 py-1.5 text-sm font-medium <?= $activeNav === 'receipts' || $activeNav === 'receipt_inbox' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
        ><?= e(t('nav.receipts')) ?>
          <?php if (\App\Infrastructure\Http\ReceiptInboxBadge::pendingCount() > 0): ?>
            <span class="absolute -top-1 -right-1 h-2 w-2 rounded-full bg-red-500"></span>
          <?php endif; ?>
        </a>
        <?php endif; ?>

        <?php if ($canArticles): ?>
        <a
          href="/bestellungen"
          class="rounded-md px-3 py-1.5 text-sm font-medium <?= $activeNav === 'purchase_orders' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
        ><?= e(t('nav.purchase_orders')) ?></a>
        <?php endif; ?>

        <?php if ($canArticles): ?>
        <a
          href="/verkaeufe"
          class="rounded-md px-3 py-1.5 text-sm font-medium <?= $activeNav === 'sales' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
        ><?= e(t('nav.sales')) ?></a>
        <?php endif; ?>

        <?php if ($canRentals): ?>
        <div class="group relative">
          <a
            href="/veranstaltungen"
            class="flex items-center gap-1 rounded-md px-3 py-1.5 text-sm font-medium <?= $isRentalsGroupActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
          >
            <?= e(t('nav.rentals')) ?>
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
          </a>
          <div class="invisible absolute left-0 top-full z-10 w-44 rounded-md border border-slate-200 bg-white py-1 opacity-0 shadow-lg transition-opacity duration-100 group-hover:visible group-hover:opacity-100 dark:border-slate-800 dark:bg-slate-900">
            <?php foreach ($rentalsChildren as $child): ?>
              <a
                href="<?= e($child['href']) ?>"
                class="block px-3 py-2 text-sm <?= $activeNav === $child['key'] ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
              ><?= e($child['label']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($canRentals): ?>
        <a
          href="/fahrzeuge"
          class="rounded-md px-3 py-1.5 text-sm font-medium <?= $activeNav === 'vehicles' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
        ><?= e(t('nav.vehicles')) ?></a>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <a
          href="/gutscheine"
          class="rounded-md px-3 py-1.5 text-sm font-medium <?= $activeNav === 'vouchers' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
        ><?= e(t('nav.vouchers')) ?></a>
        <?php endif; ?>
      </nav>

      <!-- Zahnrad-Icon und Benutzermenü: erst ab lg (1024px) sichtbar — in
           der mobilen Ansicht stehen dieselben Ziele bereits vollständig im
           Hamburger-Menü (#mobile-nav weiter unten), eine zusätzliche,
           separate Anzeige hier wäre doppelt (und das Hover-Dropdown des
           Benutzermenüs funktioniert auf Touch-Geräten ohnehin nicht gut). -->
      <div class="ml-auto hidden items-center gap-2 lg:ml-0 lg:flex">
        <a
          href="/profil/darstellung"
          aria-label="<?= e(t('nav.settings')) ?>"
          class="rounded-md border border-slate-300 p-2 <?= $activeNav === 'profile' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-500 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
        >
          <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M11.078 2.25c-.917-1-2.239-1-3.156 0l-.026.028a1.415 1.415 0 01-1.298.398 1.415 1.415 0 00-1.644 1.055 1.415 1.415 0 01-1.043 1.043 1.415 1.415 0 00-1.055 1.644c.11.494-.036 1.01-.398 1.374l-.02.02c-1 .917-1 2.239 0 3.156l.026.026c.362.363.508.88.398 1.374a1.415 1.415 0 001.055 1.644c.494.11.933.49 1.043 1.043a1.415 1.415 0 001.644 1.055c.494-.11 1.01.036 1.374.398l.02.02c.917 1 2.239 1 3.156 0l.026-.026a1.415 1.415 0 011.374-.398 1.415 1.415 0 001.644-1.055 1.415 1.415 0 011.043-1.043 1.415 1.415 0 001.055-1.644 1.415 1.415 0 01.398-1.374l.02-.02c1-.917 1-2.239 0-3.156l-.026-.026a1.415 1.415 0 01-.398-1.374 1.415 1.415 0 00-1.055-1.644 1.415 1.415 0 01-1.043-1.043 1.415 1.415 0 00-1.644-1.055 1.415 1.415 0 01-1.374-.398l-.02-.02zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
          </svg>
        </a>

        <?php if ($currentUser !== null): ?>
        <div class="group relative">
          <button
            type="button"
            aria-label="<?= e(t('nav.user_menu')) ?>"
            class="flex items-center gap-1 rounded-md border border-slate-300 py-1.5 pl-2 pr-1.5 text-sm text-slate-600 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800"
          >
            <span class="hidden max-w-[10rem] truncate sm:inline"><?= e($currentUser->displayName()) ?></span>
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
          </button>
          <div class="invisible absolute right-0 top-full z-10 w-52 rounded-md border border-slate-200 bg-white py-1 opacity-0 shadow-lg transition-opacity duration-100 group-hover:visible group-hover:opacity-100 dark:border-slate-800 dark:bg-slate-900">
            <div class="truncate border-b border-slate-100 px-3 py-2 text-xs text-slate-400 dark:border-slate-800 dark:text-slate-500"><?= e($currentUser->displayName()) ?></div>
            <a href="/profil/sicherheit" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.security_2fa')) ?></a>
            <a href="/profil/sitzungen" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.sessions')) ?></a>
            <a href="/profil/caldav" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.caldav')) ?></a>
            <a href="/profil/carddav" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.carddav')) ?></a>
            <a href="/profil/passwort" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.change_password')) ?></a>
            <a href="/profil/darstellung" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.display')) ?></a>
            <?php if ($isAdmin): ?>
              <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
              <a href="/berichte/einnahmen-ausgaben" class="block px-3 py-2 text-sm <?= $activeNav === 'reports' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800' ?>"><?= e(t('nav.reports')) ?></a>
              <a href="/benutzer" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.user_management')) ?></a>

              <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
              <?php foreach (\App\Infrastructure\Http\SettingsSections::groups() as $navSettingsGroup): ?>
                <a href="<?= e($navSettingsGroup['pages'][0]['href']) ?>" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e($navSettingsGroup['label']) ?></a>
              <?php endforeach; ?>
            <?php endif; ?>
            <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
            <form method="post" action="/logout">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
              <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-slate-50 dark:text-red-400 dark:hover:bg-slate-800"><?= e(t('nav.logout')) ?></button>
            </form>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Mobile-Navigation: unterhalb von lg (1024px) per Hamburger-Button
         ein-/ausklappbar. Da es auf Touch-Geräten kein ":hover" gibt, wird
         die Artikel-Untergruppe hier nicht als Hover-Dropdown, sondern
         direkt eingerückt als flache Liste angezeigt. -->
    <nav id="mobile-nav" class="hidden border-t border-slate-200 px-2 py-2 lg:hidden dark:border-slate-800">
      <?php if ($canArticles): ?>
      <a
        href="/artikel"
        class="block rounded-md px-3 py-2.5 text-sm font-medium <?= $isArticlesGroupActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
      ><?= e(t('nav.articles')) ?></a>
      <?php foreach ($articlesChildren as $child): ?>
        <a
          href="<?= e($child['href']) ?>"
          class="block rounded-md py-2.5 pl-7 pr-3 text-sm <?= $activeNav === $child['key'] ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
        ><?= e($child['label']) ?></a>
      <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($canCustomers): ?>
      <a
        href="/kunden"
        class="block rounded-md px-3 py-2.5 text-sm font-medium <?= $activeNav === 'customers' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
      ><?= e(t('nav.customers')) ?></a>
      <?php endif; ?>

      <?php if ($canArticles): ?>
      <a
        href="/lieferanten"
        class="block rounded-md px-3 py-2.5 text-sm font-medium <?= $activeNav === 'suppliers' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
      ><?= e(t('nav.suppliers')) ?></a>
      <?php endif; ?>

      <?php if ($canArticles): ?>
      <a
        href="/belege"
        class="relative block rounded-md px-3 py-2.5 text-sm font-medium <?= $activeNav === 'receipts' || $activeNav === 'receipt_inbox' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
      ><?= e(t('nav.receipts')) ?>
        <?php if (\App\Infrastructure\Http\ReceiptInboxBadge::pendingCount() > 0): ?>
          <span class="absolute right-3 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full bg-red-500"></span>
        <?php endif; ?>
      </a>
      <?php endif; ?>

      <?php if ($canArticles): ?>
      <a
        href="/bestellungen"
        class="block rounded-md px-3 py-2.5 text-sm font-medium <?= $activeNav === 'purchase_orders' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
      ><?= e(t('nav.purchase_orders')) ?></a>
      <?php endif; ?>

      <?php if ($canArticles): ?>
      <a
        href="/verkaeufe"
        class="block rounded-md px-3 py-2.5 text-sm font-medium <?= $activeNav === 'sales' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
      ><?= e(t('nav.sales')) ?></a>
      <?php endif; ?>

      <?php if ($canRentals): ?>
      <a
        href="/veranstaltungen"
        class="block rounded-md px-3 py-2.5 text-sm font-medium <?= $isRentalsGroupActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
      ><?= e(t('nav.rentals')) ?></a>
      <?php foreach ($rentalsChildren as $child): ?>
        <a
          href="<?= e($child['href']) ?>"
          class="block rounded-md py-2.5 pl-7 pr-3 text-sm <?= $activeNav === $child['key'] ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
        ><?= e($child['label']) ?></a>
      <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($canRentals): ?>
      <a
        href="/fahrzeuge"
        class="block rounded-md px-3 py-2.5 text-sm font-medium <?= $activeNav === 'vehicles' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
      ><?= e(t('nav.vehicles')) ?></a>
      <?php endif; ?>

      <?php if ($isAdmin): ?>
      <a
        href="/gutscheine"
        class="block rounded-md px-3 py-2.5 text-sm font-medium <?= $activeNav === 'vouchers' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
      ><?= e(t('nav.vouchers')) ?></a>
      <?php endif; ?>

      <?php if ($currentUser !== null): ?>
        <div class="my-2 border-t border-slate-200 dark:border-slate-800"></div>
        <div class="truncate px-3 pb-1.5 text-xs text-slate-400 dark:text-slate-500"><?= e($currentUser->displayName()) ?></div>
        <a href="/profil/sicherheit" class="block rounded-md px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.security_2fa')) ?></a>
        <a href="/profil/sitzungen" class="block rounded-md px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.sessions')) ?></a>
        <a href="/profil/caldav" class="block rounded-md px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.caldav')) ?></a>
        <a href="/profil/carddav" class="block rounded-md px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.carddav')) ?></a>
        <a href="/profil/passwort" class="block rounded-md px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.change_password')) ?></a>
        <a href="/profil/darstellung" class="block rounded-md px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.display')) ?></a>
        <?php if ($isAdmin): ?>
          <a href="/berichte/einnahmen-ausgaben" class="block rounded-md px-3 py-2.5 text-sm font-medium <?= $activeNav === 'reports' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"><?= e(t('nav.reports')) ?></a>
          <a href="/benutzer" class="block rounded-md px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.user_management')) ?></a>

          <div class="my-1 border-t border-slate-200 dark:border-slate-800"></div>
          <?php foreach (\App\Infrastructure\Http\SettingsSections::groups() as $navSettingsGroupMobile): ?>
            <a href="<?= e($navSettingsGroupMobile['pages'][0]['href']) ?>" class="block rounded-md px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800"><?= e($navSettingsGroupMobile['label']) ?></a>
          <?php endforeach; ?>
        <?php endif; ?>
        <form method="post" action="/logout" class="px-3 pt-1">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
          <button type="submit" class="block w-full rounded-md py-2 text-left text-sm font-medium text-red-600 hover:bg-slate-100 dark:text-red-400 dark:hover:bg-slate-800"><?= e(t('nav.logout')) ?></button>
        </form>
      <?php endif; ?>
    </nav>
  </header>

  <?php if ($impersonator !== null): ?>
    <div class="flex flex-wrap items-center justify-center gap-3 border-b border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
      <span>
        Angemeldet als <strong><?= e($currentUser?->displayName() ?? '') ?></strong>,
        ausgegeben durch <strong><?= e($impersonator->displayName()) ?></strong>.
      </span>
      <form method="post" action="/benutzer/impersonation/beenden">
        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
        <button type="submit" class="rounded-md bg-amber-600 px-3 py-1 text-xs font-medium text-white hover:bg-amber-700">
          Zurück zu eigenem Konto
        </button>
      </form>
    </div>
  <?php endif; ?>

  <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-6 sm:py-8">
    <?php if ($flashSuccess): ?>
      <div class="mb-6 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
        <?= e($flashSuccess) ?>
      </div>
    <?php endif; ?>
    <?php if ($flashError): ?>
      <div class="mb-6 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-700 dark:bg-red-950/40 dark:text-red-300">
        <?= e($flashError) ?>
      </div>
    <?php endif; ?>

    <?php $settingsGroupKey = \App\Infrastructure\Http\SettingsSections::groupKeyFor($activeNav); ?>
    <?php if ($settingsGroupKey !== null): ?>
      <?php require __DIR__ . '/settings/_tabs.php'; ?>
    <?php endif; ?>

    <?= $content ?>
  </main>

  <footer class="mt-auto border-t border-slate-200 bg-white py-4 dark:border-slate-800 dark:bg-slate-900">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-center gap-y-1 px-4 text-xs text-slate-500 dark:text-slate-400">
      <a href="<?= e($imprintHref) ?>"<?= $imprintExternal ? ' target="_blank" rel="noopener noreferrer"' : '' ?> class="hover:underline"><?= e(t('footer.imprint')) ?></a>
      <span class="px-2">|</span>
      <a href="<?= e($privacyHref) ?>"<?= $privacyExternal ? ' target="_blank" rel="noopener noreferrer"' : '' ?> class="hover:underline"><?= e(t('footer.privacy')) ?></a>
    </div>
  </footer>

  <script>
    // Hamburger-Button: klappt die mobile Navigation (< lg / 1024px) auf
    // und zu, tauscht Hamburger-/Kreuz-Icon und hält aria-expanded aktuell.
    // Ab lg blendet die "lg:hidden"-Klasse auf #mobile-nav das Panel
    // ohnehin immer aus, unabhängig vom hier gesetzten "hidden"-Zustand.
    (function () {
      var toggle = document.getElementById('mobile-menu-toggle');
      var nav = document.getElementById('mobile-nav');
      var iconOpen = document.getElementById('mobile-menu-icon-open');
      var iconClose = document.getElementById('mobile-menu-icon-close');

      toggle.addEventListener('click', function () {
        var isOpen = !nav.classList.contains('hidden');
        nav.classList.toggle('hidden', isOpen);
        iconOpen.classList.toggle('hidden', !isOpen);
        iconClose.classList.toggle('hidden', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      });
    })();

    // Hinweis: Der Hell-/Dunkelmodus-Umschalter selbst befindet sich nicht
    // mehr im Header, sondern auf der Seite /profil/darstellung (siehe
    // resources/views/profile/display.php) — dort steckt auch die
    // dazugehörige JS-Logik (liest/schreibt weiterhin denselben
    // localStorage-Schlüssel "theme" wie das Skript im <head> oben).

    // Gemeinsamer Helfer für AJAX-paginierte Listen (Artikel, Veranstaltungen,
    // Kunden, Veranstaltungsorte, Cases, Lieferanten, Benutzer, ...). Jede
    // Listen-Seite ruft ihn einmal mit ihrem Container- und ggf.
    // Filterformular-Selektor auf (siehe z. B. resources/views/articles/
    // index.php). Serverseitig liefert derselbe Controller/dieselbe Route
    // bei "X-Requested-With: XMLHttpRequest" nur das Tabellen-Fragment
    // (_table.php) statt der vollen Seite zurück (siehe Pagination::isAjax()).
    function initPaginatedList(options) {
      var container = document.querySelector(options.container);
      var form = options.form ? document.querySelector(options.form) : null;
      var endpoint = options.endpoint;

      if (!container || !endpoint) {
        return;
      }

      function currentParams(extra) {
        var params = new URLSearchParams(window.location.search);

        if (form) {
          Array.prototype.forEach.call(form.elements, function (el) {
            if (el.name) {
              params.delete(el.name);
            }
          });
          var formData = new FormData(form);
          formData.forEach(function (value, key) {
            if (value !== '') {
              params.set(key, value);
            } else {
              params.delete(key);
            }
          });
        }

        Object.keys(extra || {}).forEach(function (key) {
          params.set(key, extra[key]);
        });

        return params;
      }

      function bindContainerEvents() {
        container.querySelectorAll('[data-page-link]').forEach(function (link) {
          link.addEventListener('click', function (e) {
            if (link.classList.contains('pointer-events-none')) {
              return;
            }
            e.preventDefault();
            load(currentParams({ page: link.getAttribute('data-page') }), true);
          });
        });

        container.querySelectorAll('[data-page-size-select]').forEach(function (select) {
          select.addEventListener('change', function () {
            load(currentParams({ pageSize: select.value, page: '1' }), true);
          });
        });
      }

      function load(params, pushUrl) {
        container.classList.add('opacity-50', 'pointer-events-none');

        var url = endpoint + '?' + params.toString();

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (res) { return res.text(); })
          .then(function (html) {
            container.innerHTML = html;
            container.classList.remove('opacity-50', 'pointer-events-none');
            if (pushUrl) {
              window.history.pushState({ paginatedList: true }, '', url);
            }
            bindContainerEvents();
          })
          .catch(function () {
            container.classList.remove('opacity-50', 'pointer-events-none');
          });
      }

      if (form) {
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          load(currentParams({ page: '1' }), true);
        });
      }

      bindContainerEvents();

      window.addEventListener('popstate', function () {
        // Bei Zurück/Vor NICHT die aktuellen Formularwerte übernehmen
        // (die sind ggf. veraltet) — stattdessen direkt aus der bereits
        // vom Browser aktualisierten URL lesen und Formularfelder danach
        // synchronisieren.
        var params = new URLSearchParams(window.location.search);
        if (form) {
          Array.prototype.forEach.call(form.elements, function (el) {
            if (el.name && params.has(el.name)) {
              el.value = params.get(el.name);
            } else if (el.name) {
              el.value = '';
            }
          });
        }
        load(params, false);
      });
    }

    // Globales Bestätigungs-Modal (ersetzt window.confirm() im gesamten
    // Frontend, siehe <dialog id="global-confirm-dialog"> oben im <body>).
    // window.confirmModal(message, style) liefert ein Promise<boolean>,
    // analog zum Rückgabewert von confirm(). style === 'danger' färbt den
    // Bestätigen-Button rot (für zerstörerische Aktionen), sonst brand-blau.
    (function () {
      var dialog = document.getElementById('global-confirm-dialog');
      var messageEl = document.getElementById('global-confirm-message');
      var okBtn = document.getElementById('global-confirm-ok');
      var cancelBtn = document.getElementById('global-confirm-cancel');

      var OK_DEFAULT_CLASSES = ['bg-brand-600', 'hover:bg-brand-700'];
      var OK_DANGER_CLASSES = ['bg-red-600', 'hover:bg-red-700'];

      window.confirmModal = function (message, style) {
        return new Promise(function (resolve) {
          messageEl.textContent = message;

          okBtn.classList.remove.apply(okBtn.classList, OK_DEFAULT_CLASSES.concat(OK_DANGER_CLASSES));
          okBtn.classList.add.apply(okBtn.classList, style === 'danger' ? OK_DANGER_CLASSES : OK_DEFAULT_CLASSES);

          var settled = false;

          function cleanup(result) {
            if (settled) {
              return;
            }
            settled = true;
            dialog.removeEventListener('close', onClose);
            okBtn.removeEventListener('click', onOk);
            cancelBtn.removeEventListener('click', onCancel);
            resolve(result);
          }

          function onOk() {
            dialog.close();
            cleanup(true);
          }

          function onCancel() {
            dialog.close();
            cleanup(false);
          }

          function onClose() {
            // Auch beim Schließen per Escape-Taste oder Klick auf das
            // Backdrop (kein Klick auf Bestätigen/Abbrechen) sauber mit
            // "false" auflösen.
            cleanup(false);
          }

          okBtn.addEventListener('click', onOk);
          cancelBtn.addEventListener('click', onCancel);
          dialog.addEventListener('close', onClose);

          dialog.showModal();
        });
      };

      // Formulare mit [data-confirm] verwenden das globale Modal statt
      // window.confirm(). Wird der Absenden-Button (oder eine Umschalttaste-
      // Variante davon) bei GEDRÜCKTER Umschalttaste (Shift) ausgelöst, wird
      // die Rückfrage komplett übersprungen und sofort abgesendet.
      document.addEventListener('click', function (e) {
        var target = e.target.closest('button[type="submit"], input[type="submit"]');
        if (!target) {
          return;
        }
        var form = target.closest('form[data-confirm]');
        if (!form) {
          return;
        }
        form.dataset.skipConfirm = e.shiftKey ? '1' : '0';
      }, true);

      document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm')) {
          return;
        }

        var skip = form.dataset.skipConfirm === '1';
        delete form.dataset.skipConfirm;

        if (skip) {
          // Shift-Bypass: Formular ganz normal absenden lassen, kein
          // preventDefault().
          return;
        }

        e.preventDefault();

        var message = form.getAttribute('data-confirm');
        var style = form.getAttribute('data-confirm-style');

        window.confirmModal(message, style).then(function (confirmed) {
          if (confirmed) {
            // .submit() statt .requestSubmit(), damit KEIN neues
            // "submit"-Ereignis ausgelöst wird (siehe WHATWG-Spezifikation)
            // — sonst würde dieser Handler sich selbst erneut aufrufen.
            form.submit();
          }
        });
      });

      // Aktions-/"..."-Dropdowns (<details> mit absolut positioniertem
      // Menü, z. B. in Listen mit vielen Zeilen-Aktionen oder "Per E-Mail
      // senden"-Formularen) schließen sich beim Klick außerhalb oder mit
      // Escape — natives <details> kennt das von Haus aus nicht. Bewusst
      // EIN globaler Listener statt einem pro Dropdown (siehe data-confirm
      // oben), damit neu ins DOM eingefügte Dropdowns (z. B. nach einem
      // Formular-Fehler-Re-Render) automatisch mit abgedeckt sind.
      document.addEventListener('click', function (e) {
        document.querySelectorAll('details[open]').forEach(function (details) {
          if (!details.contains(e.target)) {
            details.removeAttribute('open');
          }
        });
      });

      // Zeilen-Aktions-Dropdowns in Tabellen (<details data-dropdown>, z. B.
      // in warehouses/_table.php, venues/_table.php, users/_table.php)
      // stecken in einem horizontal scrollbaren .overflow-x-auto-Wrapper
      // (siehe dort — nötig, damit breite Tabellen auf schmalen Bildschirmen
      // nicht das Layout sprengen). Ein rein per CSS position:absolute
      // positioniertes Menü wird von diesem Vorfahren abgeschnitten bzw.
      // falsch platziert dargestellt, da laut CSS-Overflow-Spezifikation ein
      // gesetztes overflow-x zusammen mit einem noch "visible" overflow-y
      // effektiv auch overflow-y auf "auto" schaltet — das Menü kann also
      // nicht mehr aus dem Scroll-Container herausragen. Fix: beim Öffnen
      // wird das Menü stattdessen per position:fixed anhand der
      // tatsächlichen Bildschirmposition des Auslösers (<summary>)
      // platziert, wodurch es jedes Overflow-Clipping der Vorfahren
      // umgeht; beim Schließen werden die Inline-Styles wieder entfernt,
      // sodass ohne JavaScript weiterhin die CSS-Fallback-Positionierung
      // (absolute/right-0/top-full o. ä.) greift.
      function positionDropdownMenu(details) {
        var summary = details.querySelector('summary');
        var menu = details.querySelector('[data-dropdown-menu]');
        if (!summary || !menu) {
          return;
        }

        var summaryRect = summary.getBoundingClientRect();
        var menuRect = menu.getBoundingClientRect();
        var spaceBelow = window.innerHeight - summaryRect.bottom;
        var openUpward = spaceBelow < menuRect.height && summaryRect.top > spaceBelow;

        menu.style.position = 'fixed';
        if (openUpward) {
          menu.style.top = '';
          menu.style.bottom = (window.innerHeight - summaryRect.top + 4) + 'px';
        } else {
          menu.style.top = (summaryRect.bottom + 4) + 'px';
          menu.style.bottom = '';
        }

        var left = summaryRect.right - menuRect.width;
        left = Math.max(8, Math.min(left, window.innerWidth - menuRect.width - 8));
        menu.style.left = left + 'px';
        menu.style.right = 'auto';
      }

      function resetDropdownMenuPosition(details) {
        var menu = details.querySelector('[data-dropdown-menu]');
        if (!menu) {
          return;
        }
        menu.style.position = '';
        menu.style.top = '';
        menu.style.bottom = '';
        menu.style.left = '';
        menu.style.right = '';
      }

      document.querySelectorAll('details[data-dropdown]').forEach(function (details) {
        details.addEventListener('toggle', function () {
          if (details.open) {
            positionDropdownMenu(details);
          } else {
            resetDropdownMenuPosition(details);
          }
        });
      });

      // Scrollt die Seite (oder der Tabellen-Wrapper) während ein Dropdown
      // offen ist, würde die per position:fixed berechnete Position sonst
      // stehen bleiben und nicht mehr zum Auslöser passen — einfacher und
      // robuster als laufend neu zu positionieren: das Dropdown wird
      // stattdessen einfach geschlossen (capture:true, da der Scroll des
      // .overflow-x-auto-Wrappers selbst nicht bis zum document bubbelt).
      document.addEventListener('scroll', function () {
        document.querySelectorAll('details[data-dropdown][open]').forEach(function (details) {
          details.removeAttribute('open');
          resetDropdownMenuPosition(details);
        });
      }, true);

      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
          return;
        }
        document.querySelectorAll('details[open]').forEach(function (details) {
          details.removeAttribute('open');
        });
      });
    })();
  </script>
</body>
</html>
