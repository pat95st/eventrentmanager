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
// Menüpunkt. Lagerorte gehören organisatorisch ebenfalls zur Artikel-
// verwaltung und stecken daher hier (nicht mehr bei "Veranstaltungen").
// Jeder Menüpunkt wird nur angezeigt, wenn der angemeldete Benutzer das
// zugehörige Recht besitzt (siehe Zuordnung in bootstrap.php).
$articlesGroupKeys = ['articles', 'sets', 'cases', 'racks', 'manufacturers', 'arbeitszeit', 'categories', 'rma', 'electrical-inspection', 'firmware', 'notice_documents', 'warehouses'];
$articlesChildren = [
    ['key' => 'articles', 'label' => t('nav.articles_all'), 'href' => '/artikel'],
    ['key' => 'sets', 'label' => t('nav.sets'), 'href' => '/sets'],
    ['key' => 'cases', 'label' => t('nav.cases'), 'href' => '/cases'],
    ['key' => 'racks', 'label' => t('nav.racks'), 'href' => '/racks'],
    ['key' => 'manufacturers', 'label' => t('nav.manufacturers'), 'href' => '/hersteller'],
    ['key' => 'arbeitszeit', 'label' => t('nav.labor_time_articles'), 'href' => '/arbeitszeit-artikel'],
    ['key' => 'categories', 'label' => t('nav.categories'), 'href' => '/kategorien'],
    ['key' => 'rma', 'label' => t('nav.rma'), 'href' => '/rma'],
    ['key' => 'electrical-inspection', 'label' => 'Elektroprüfung', 'href' => '/elektropruefung'],
    ['key' => 'firmware', 'label' => t('nav.firmware'), 'href' => '/firmware'],
    ['key' => 'notice_documents', 'label' => t('nav.notice_documents'), 'href' => '/hinweisdokumente'],
    ['key' => 'warehouses', 'label' => t('nav.warehouses'), 'href' => '/lagerorte'],
];
$isArticlesGroupActive = in_array($activeNav, $articlesGroupKeys, true);

// "Kontakte" fasst die beiden externen Adressbestände (Kunden, Lieferanten)
// unter einem gemeinsamen Oberpunkt zusammen. Die beiden Kindpunkte haben
// unterschiedliche Rechte (Kundenverwaltung bzw. Artikelverwaltung) — der
// Oberpunkt selbst ist bereits sichtbar, sobald mindestens eines davon
// zutrifft (siehe $canCustomers/$canArticles weiter unten).
$contactsGroupKeys = ['customers', 'suppliers'];
$contactsChildren = [
    ['key' => 'customers', 'label' => t('nav.customers'), 'href' => '/kunden'],
    ['key' => 'suppliers', 'label' => t('nav.suppliers'), 'href' => '/lieferanten'],
];
$isContactsGroupActive = in_array($activeNav, $contactsGroupKeys, true);

// "Einkauf" fasst Belege (Beleg-Posteingang) und Bestellungen unter einem
// gemeinsamen Oberpunkt zusammen — beide gehören zum Wareneinkauf bei
// Lieferanten und waren bisher gleichrangige, einzelne Menüpunkte.
$procurementGroupKeys = ['receipts', 'receipt_inbox', 'purchase_orders'];
$procurementChildren = [
    ['key' => 'receipts', 'label' => t('nav.receipts'), 'href' => '/belege'],
    ['key' => 'purchase_orders', 'label' => t('nav.purchase_orders'), 'href' => '/bestellungen'],
];
$isProcurementGroupActive = in_array($activeNav, $procurementGroupKeys, true) || $activeNav === 'receipt_inbox';

// "Dokumente" sammelt die kaufmännischen Dokumente (Angebote, Auftrags-
// bestätigungen, Rechnungen) sowie den Zahlungsabgleich — bewusst GETRENNT
// von "Veranstaltungen", da diese Dokumente eigenständig (nicht nur aus
// einer Veranstaltung heraus) angelegt werden können.
$documentsGroupKeys = ['offers', 'order_confirmations', 'invoices', 'invoice-corrections', 'bank_import', 'accounts'];
$documentsChildren = [
    ['key' => 'offers', 'label' => t('nav.offers'), 'href' => '/angebote'],
    ['key' => 'order_confirmations', 'label' => t('nav.order_confirmations'), 'href' => '/auftragsbestaetigungen'],
    ['key' => 'invoices', 'label' => t('nav.invoices'), 'href' => '/rechnungen'],
    ['key' => 'invoice-corrections', 'label' => 'Rechnungskorrekturen', 'href' => '/rechnungskorrekturen'],
    ['key' => 'bank_import', 'label' => t('nav.bank_import'), 'href' => '/zahlungsabgleich'],
    ['key' => 'accounts', 'label' => 'Kontoführung', 'href' => '/konten'],
];
$isDocumentsGroupActive = in_array($activeNav, $documentsGroupKeys, true);

// "Veranstaltung" ist analog zu "Artikel" ein Menüpunkt mit Untermenü
// (Veranstaltungsorte und Kalender sind der Veranstaltungsverwaltung
// untergeordnet) — enthält nach der Trennung von den kaufmännischen
// Dokumenten (siehe "Dokumente" oben) und Lagerorte (siehe "Artikel" oben)
// nur noch die eigentliche Veranstaltungsplanung.
$rentalsGroupKeys = ['rentals', 'venues', 'calendar', 'minute_program_templates'];
$rentalsChildren = [
    ['key' => 'rentals', 'label' => t('nav.rentals_all'), 'href' => '/veranstaltungen'],
    ['key' => 'venues', 'label' => t('nav.venues'), 'href' => '/veranstaltungsorte'],
    ['key' => 'calendar', 'label' => t('nav.calendar'), 'href' => '/kalender'],
    ['key' => 'minute_program_templates', 'label' => t('nav.minute_program_templates'), 'href' => '/minutenprogramm-vorlagen'],
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
// Eigener Menüpunkt "Elektroprüfung" für Benutzer mit diesem Recht, aber ohne
// Artikelverwaltung (sonst steht er im Menü "Artikel").
$canInspections = $currentUser !== null && !$canArticles && $currentUser->has(Permission::Elektropruefung);
// Nur-Techniker (Migration 134): eigener Menüpunkt „Meine Veranstaltungen“.
$canTechnicianRentals = !$canRentals && \App\Domain\Technician\TechnicianAccess::isRestricted($currentUser);
// "Kontakte" ist sichtbar, sobald mindestens einer der beiden Kindpunkte
// (Kunden ODER Lieferanten) berechtigt ist; "Einkauf"/"Dokumente" teilen
// sich jeweils ein einziges Recht mit allen ihren Kindpunkten (siehe oben).
$canContacts = $canCustomers || $canArticles;
$canProcurement = $canArticles;
$canDocuments = $canRentals;
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
<link rel="stylesheet" href="/assets/css/app.css?v=<?= (string) (@filemtime(dirname(__DIR__, 2) . '/public/assets/css/app.css') ?: 1) ?>">
<?= class_exists(\App\Infrastructure\Http\UiFonts::class) ? \App\Infrastructure\Http\UiFonts::styles() : '' /* Schriftarten der Oberfläche */ ?>
<style>
  /* Einblendfenster (Modals): einheitliches Aussehen für beide Arten —
     Overlays (<div class="fixed inset-0 z-50">) und native <dialog>.
     Klassen wie bg-black/40 oder backdrop:… fehlen im kompilierten
     app.css, daher zentral hier. Auf dem Handy erscheinen sie als
     Blatt, das von unten hereinfährt (leichter mit dem Daumen erreichbar). */
  .fixed.inset-0.z-50 { background-color: rgba(15, 23, 42, 0.5); padding: 1.5rem; }
  dialog::backdrop { background-color: rgba(15, 23, 42, 0.5); }
  @supports ((backdrop-filter: blur(3px)) or (-webkit-backdrop-filter: blur(3px))) {
    .fixed.inset-0.z-50, dialog::backdrop { -webkit-backdrop-filter: blur(3px) saturate(.8); backdrop-filter: blur(3px) saturate(.8); }
  }
  .modal-panel {
    width: 100%; max-width: 40rem; max-height: 88vh;
    border: 0 !important; border-radius: 1rem !important;
    box-shadow: 0 0 0 1px rgba(15, 23, 42, 0.06), 0 24px 48px -12px rgba(15, 23, 42, 0.35), 0 8px 16px -8px rgba(15, 23, 42, 0.2) !important;
  }
  html.dark .modal-panel { box-shadow: 0 0 0 1px rgba(148, 163, 184, 0.14), 0 24px 48px -12px rgba(0, 0, 0, 0.7) !important; }
  .modal-panel.modal-panel-lg { max-width: 52rem; }
  .modal-panel.modal-panel-sm { max-width: 28rem; }
  dialog.modal-panel { margin: auto; overflow: auto; padding: 0; }
  .modal-panel.p-4, .modal-panel.p-5, .modal-panel > .p-4, .modal-panel > form.p-4 { padding: 1.5rem; }
  .modal-panel > .px-4 { padding-left: 1.5rem; padding-right: 1.5rem; }
  .modal-panel > .py-3 { padding-top: 1rem; padding-bottom: 1rem; }
  /* Kopfzeile (erstes Kind mit Trennlinie): ruhiger, Titel größer. */
  .modal-panel > .border-b:first-child { border-color: rgb(241 245 249); padding-top: 1.125rem; padding-bottom: 1.125rem; }
  html.dark .modal-panel > .border-b:first-child { border-color: rgb(30 41 59); }
  .modal-panel > .border-b:first-child h2, .modal-panel > .border-b:first-child h3 { font-size: 1rem; line-height: 1.5rem; font-weight: 600; }
  /* Fußzeile (letztes Kind mit Trennlinie): leicht abgesetzt. */
  .modal-panel > .border-t:last-child { border-color: rgb(241 245 249); background-color: rgb(248 250 252); border-radius: 0 0 1rem 1rem; }
  html.dark .modal-panel > .border-t:last-child { border-color: rgb(30 41 59); background-color: rgb(15 23 42 / 0.6); }
  /* Schließen-Kreuz: großzügige, runde Klickfläche statt eines winzigen „×“. */
  .modal-panel > .border-b:first-child > button:last-child {
    display: inline-flex; align-items: center; justify-content: center;
    width: 2rem; height: 2rem; margin: -0.25rem -0.5rem -0.25rem 0; border-radius: 9999px;
    font-size: 1.375rem; line-height: 1; color: rgb(100 116 139);
  }
  .modal-panel > .border-b:first-child > button:last-child:hover { background-color: rgb(241 245 249); color: rgb(15 23 42); }
  html.dark .modal-panel > .border-b:first-child > button:last-child:hover { background-color: rgb(30 41 59); color: rgb(241 245 249); }
  .modal-panel > .border-b:first-child > button:last-child:focus-visible { outline: 2px solid rgb(var(--color-brand-500)); outline-offset: 1px; }

  @media (max-width: 640px) {
    .fixed.inset-0.z-50 { padding: 0; align-items: flex-end !important; }
    .modal-panel { max-width: none !important; max-height: 92vh; border-radius: 1rem 1rem 0 0 !important; }
    .modal-panel > .border-t:last-child { border-radius: 0; padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
    dialog.modal-panel { margin: auto 0 0; width: 100%; max-width: 100% !important; }
    .modal-panel.p-4, .modal-panel.p-5, .modal-panel > .p-4, .modal-panel > form.p-4 { padding: 1.25rem; }
    .modal-panel > .px-4 { padding-left: 1.25rem; padding-right: 1.25rem; }
    .modal-panel::before { content: ""; display: block; width: 2.5rem; height: 0.25rem; margin: 0.5rem auto -0.25rem; border-radius: 9999px; background: rgb(203 213 225); }
    html.dark .modal-panel::before { background: rgb(71 85 105); }
  }

  /* Bestätigungsdialog (siehe #global-confirm-dialog und window.confirmModal) */
  .confirm-icon { display: inline-flex; flex: none; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 9999px; }
  .confirm-icon svg { width: 1.25rem; height: 1.25rem; }
  .confirm-icon-default { background-color: rgb(var(--color-brand-100)); color: rgb(var(--color-brand-700)); }
  .confirm-icon-danger { background-color: rgb(254 226 226); color: rgb(220 38 38); }
  html.dark .confirm-icon-default { background-color: rgb(var(--color-brand-950) / 0.6); color: rgb(var(--color-brand-300)); }
  html.dark .confirm-icon-danger { background-color: rgb(127 29 29 / 0.45); color: rgb(252 165 165); }

  /* Weiches Ein-/Ausblenden, siehe /assets/js/modal-fade.js. Das Fenster
     selbst wächst leicht aus der Mitte (Handy: fährt von unten herein),
     der Hintergrund blendet nur über. */
  .modal-fade { transition: opacity 200ms ease; }
  .modal-fade > .modal-panel { transition: transform 240ms cubic-bezier(.16, 1, .3, 1); }
  .modal-fade.modal-fade-out { opacity: 0; }
  .modal-fade.modal-fade-out > .modal-panel { transform: translateY(8px) scale(.96); }
  @keyframes modal-fade-in { from { opacity: 0; transform: translateY(8px) scale(.96); } to { opacity: 1; transform: none; } }
  @keyframes modal-fade-out { from { opacity: 1; transform: none; } to { opacity: 0; transform: translateY(8px) scale(.96); } }
  @keyframes modal-backdrop-in { from { opacity: 0; } to { opacity: 1; } }
  @keyframes modal-backdrop-out { from { opacity: 1; } to { opacity: 0; } }
  dialog[open] { animation: modal-fade-in 240ms cubic-bezier(.16, 1, .3, 1); }
  dialog[open]::backdrop { animation: modal-backdrop-in 200ms ease; }
  dialog.modal-closing { animation: modal-fade-out 180ms ease forwards; }
  dialog.modal-closing::backdrop { animation: modal-backdrop-out 180ms ease forwards; }
  @media (max-width: 640px) {
    .modal-fade.modal-fade-out > .modal-panel { transform: translateY(100%); }
    @keyframes modal-sheet-in { from { transform: translateY(100%); } to { transform: none; } }
    @keyframes modal-sheet-out { from { transform: none; } to { transform: translateY(100%); } }
    dialog[open] { animation-name: modal-sheet-in; }
    dialog.modal-closing { animation-name: modal-sheet-out; }
  }
  @media (prefers-reduced-motion: reduce) {
    dialog[open], dialog[open]::backdrop, dialog.modal-closing, dialog.modal-closing::backdrop { animation: none; }
    .modal-fade, .modal-fade > .modal-panel { transition: none; }
    .modal-fade.modal-fade-out > .modal-panel { transform: none; }
  }
  /* Untermenüs der Hauptnavigation: Breite nach dem längsten Eintrag (auch
     bei breiteren Schriftarten) mit gleichem Abstand links und rechts; mit
     Hintergrundbild nahezu deckend, damit der Inhalt darunter nicht durchscheint. */
  /* Geöffnete Dropdowns immer über Karten/Filterleisten (z-20 fehlt im kompilierten CSS). */
  details[data-dropdown] > [data-dropdown-menu] { z-index: 60 !important; }
  [data-dropdown-menu] { overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
  .nav-flyout { width: max-content; min-width: 11rem; max-width: 22rem; }
  .nav-flyout > a { padding-left: 0.875rem; padding-right: 0.875rem; white-space: nowrap; }
  body.has-bg-image .nav-flyout, body.has-bg-image [data-dropdown-menu], body.has-bg-image [data-live-search-results] { background-color: rgb(255 255 255) !important; }
  html.dark body.has-bg-image .nav-flyout, html.dark body.has-bg-image [data-dropdown-menu], html.dark body.has-bg-image [data-live-search-results] { background-color: rgb(15 23 42) !important; box-shadow: 0 10px 25px -5px rgb(0 0 0 / 0.6), 0 0 0 1px rgb(71 85 105 / 0.6); }
  /* Mobiles Menü im (sticky) Header: eigene Scrollfläche, sonst ist alles
     unterhalb des Bildschirmrands auf dem Handy nicht erreichbar. Die
     genaue Höhe setzt das Hamburger-Skript beim Öffnen. */
  #mobile-nav { max-height: calc(100vh - 4rem); max-height: calc(100dvh - 4rem); overflow-y: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
</style>
<script src="/assets/js/modal-fade.js"></script>
<script src="/assets/js/sortable-tables.js" defer></script>
<script src="/assets/js/pdf-viewer.js" defer></script>
<script src="/assets/js/row-actions.js" defer></script>
<?php if (($currentUser ?? null) !== null): /* Hinweis vor automatischer Abmeldung (Einstellungen › Sicherheit) */ ?>
<script>window.SESSION_TIMEOUT_SECONDS = <?= (int) \App\Infrastructure\Http\Auth\SessionTimeout::seconds() ?>;</script>
<script src="/assets/js/session-timeout.js" defer></script>
<?php endif; ?>
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
>
  <?= \App\Infrastructure\Http\BackgroundLayer::render(\App\Domain\Settings\BackgroundSlot::App) ?>
  <!--
    Globales Bestätigungs-Modal: ersetzt window.confirm() im gesamten
    Frontend (siehe window.confirmModal()-Helfer weiter unten). Formulare
    markieren sich mit data-confirm="Frage..." (optional data-confirm-
    style="danger" für den roten Bestätigen-Button) statt eines
    onsubmit="return confirm(...)"-Attributs.
  -->
  <dialog id="global-confirm-dialog" class="modal-panel modal-panel-sm w-full bg-white dark:bg-slate-900" aria-labelledby="global-confirm-title" aria-describedby="global-confirm-message">
    <div class="flex gap-4 px-6 pb-5 pt-6">
      <span id="global-confirm-icon" class="confirm-icon confirm-icon-default" aria-hidden="true">
        <svg data-icon="default" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zM8.94 6.94a.75.75 0 11-1.061-1.061 3 3 0 112.871 5.026v.345a.75.75 0 01-1.5 0v-.5c0-.72.57-1.172 1.081-1.287A1.5 1.5 0 108.94 6.94zM10 15a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
        <svg data-icon="danger" class="hidden" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
      </span>
      <div class="min-w-0 pt-1.5">
        <h2 id="global-confirm-title" class="text-base font-semibold text-slate-900 dark:text-slate-100"><?= e(t('confirm.title')) ?></h2>
        <p id="global-confirm-message" class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-400"></p>
      </div>
    </div>
    <div class="flex flex-col-reverse gap-2 border-t px-6 py-4 sm:flex-row sm:justify-end">
      <button type="button" id="global-confirm-cancel" class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('common.cancel')) ?></button>
      <button type="button" id="global-confirm-ok" class="rounded-md px-4 py-2 text-sm font-medium text-white"><?= e(t('confirm.ok')) ?></button>
    </div>
  </dialog>
  <header class="sticky top-0 z-30 border-b border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" style="position:sticky;top:0;z-index:30">
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

      <a href="/" class="flex items-center gap-2 font-semibold text-slate-900 dark:text-slate-100">
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
          <div class="nav-flyout invisible absolute left-0 top-full z-10 rounded-md border border-slate-200 bg-white py-1 opacity-0 shadow-lg transition-opacity duration-100 group-hover:visible group-hover:opacity-100 dark:border-slate-800 dark:bg-slate-900">
            <?php foreach ($articlesChildren as $child): ?>
              <a
                href="<?= e($child['href']) ?>"
                class="block px-3 py-2 text-sm <?= $activeNav === $child['key'] ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
              ><?= e($child['label']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($canContacts): ?>
        <div class="group relative">
          <a
            href="/kunden"
            class="flex items-center gap-1 rounded-md px-3 py-1.5 text-sm font-medium <?= $isContactsGroupActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
          >
            <?= e(t('nav.contacts')) ?>
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
          </a>
          <div class="nav-flyout invisible absolute left-0 top-full z-10 rounded-md border border-slate-200 bg-white py-1 opacity-0 shadow-lg transition-opacity duration-100 group-hover:visible group-hover:opacity-100 dark:border-slate-800 dark:bg-slate-900">
            <?php foreach ($contactsChildren as $child): ?>
              <a
                href="<?= e($child['href']) ?>"
                class="block px-3 py-2 text-sm <?= $activeNav === $child['key'] ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
              ><?= e($child['label']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($canProcurement): ?>
        <div class="group relative">
          <a
            href="/belege"
            class="relative flex items-center gap-1 rounded-md px-3 py-1.5 text-sm font-medium <?= $isProcurementGroupActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
          >
            <?= e(t('nav.procurement')) ?>
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
            <?php if (\App\Infrastructure\Http\ReceiptInboxBadge::pendingCount() > 0): ?>
              <span class="absolute -top-1 -right-1 h-2 w-2 rounded-full bg-red-500"></span>
            <?php endif; ?>
          </a>
          <div class="nav-flyout invisible absolute left-0 top-full z-10 rounded-md border border-slate-200 bg-white py-1 opacity-0 shadow-lg transition-opacity duration-100 group-hover:visible group-hover:opacity-100 dark:border-slate-800 dark:bg-slate-900">
            <?php foreach ($procurementChildren as $child): ?>
              <a
                href="<?= e($child['href']) ?>"
                class="relative block px-3 py-2 text-sm <?= $activeNav === $child['key'] ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
              ><?= e($child['label']) ?>
                <?php if ($child['key'] === 'receipts' && \App\Infrastructure\Http\ReceiptInboxBadge::pendingCount() > 0): ?>
                  <span class="absolute right-2 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full bg-red-500"></span>
                <?php endif; ?>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($canArticles): ?>
        <a
          href="/verkaeufe"
          class="rounded-md px-3 py-1.5 text-sm font-medium <?= $activeNav === 'sales' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
        ><?= e(t('nav.sales')) ?></a>
        <?php endif; ?>

        <?php if ($canTechnicianRentals): ?>
        <a
          href="/veranstaltungen"
          class="rounded-md px-3 py-1.5 text-sm font-medium <?= $activeNav === 'rentals' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
        >Meine Veranstaltungen</a>
        <?php endif; ?>

        <?php if ($canInspections): ?>
        <a
          href="/elektropruefung"
          class="rounded-md px-3 py-1.5 text-sm font-medium <?= $activeNav === 'electrical-inspection' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
        >Elektroprüfung</a>
        <?php endif; ?>

        <?php if ($canDocuments): ?>
        <div class="group relative">
          <a
            href="/angebote"
            class="flex items-center gap-1 rounded-md px-3 py-1.5 text-sm font-medium <?= $isDocumentsGroupActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
          >
            <?= e(t('nav.documents')) ?>
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd"/></svg>
          </a>
          <div class="nav-flyout invisible absolute left-0 top-full z-10 rounded-md border border-slate-200 bg-white py-1 opacity-0 shadow-lg transition-opacity duration-100 group-hover:visible group-hover:opacity-100 dark:border-slate-800 dark:bg-slate-900">
            <?php foreach ($documentsChildren as $child): ?>
              <a
                href="<?= e($child['href']) ?>"
                class="block px-3 py-2 text-sm <?= $activeNav === $child['key'] ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
              ><?= e($child['label']) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
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
          <div class="nav-flyout invisible absolute left-0 top-full z-10 rounded-md border border-slate-200 bg-white py-1 opacity-0 shadow-lg transition-opacity duration-100 group-hover:visible group-hover:opacity-100 dark:border-slate-800 dark:bg-slate-900">
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

      <?php if ($currentUser !== null): ?>
      <!-- Mobiles Einstellungsmenü: ab < lg (1024px) sichtbar, ganz rechts
           im Header (ml-auto, da desktop-nav/Zahnrad+Benutzermenü hier auf
           Mobilgeräten "hidden" sind und daher keinen Platz einnehmen).
           Öffnet per Klick (statt Hover wie beim Desktop-Benutzermenü
           weiter unten — Touch-Geräte kennen kein ":hover") dieselben
           Profil-/Admin-Links als eigenes Menü, statt sie wie bisher nur
           eingebettet im langen Hamburger-Hauptmenü zu zeigen. Nutzt
           <details data-dropdown>, also dieselbe Dropdown-Mechanik (inkl.
           Positionierung/Escape/Klick-außerhalb) wie die Zeilen-Aktions-
           Menüs in Tabellen (siehe JS weiter unten). -->
      <details class="relative ml-auto lg:hidden" data-dropdown>
        <summary
          aria-label="<?= e(t('nav.user_menu')) ?>"
          class="flex h-9 w-9 cursor-pointer list-none items-center justify-center rounded-md border border-slate-300 text-slate-500 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-400 dark:hover:bg-slate-800 <?= $activeNav === 'profile' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : '' ?>"
        >
          <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M11.078 2.25c-.917-1-2.239-1-3.156 0l-.026.028a1.415 1.415 0 01-1.298.398 1.415 1.415 0 00-1.644 1.055 1.415 1.415 0 01-1.043 1.043 1.415 1.415 0 00-1.055 1.644c.11.494-.036 1.01-.398 1.374l-.02.02c-1 .917-1 2.239 0 3.156l.026.026c.362.363.508.88.398 1.374a1.415 1.415 0 001.055 1.644c.494.11.933.49 1.043 1.043a1.415 1.415 0 001.644 1.055c.494-.11 1.01.036 1.374.398l.02.02c.917 1 2.239 1 3.156 0l.026-.026a1.415 1.415 0 011.374-.398 1.415 1.415 0 001.644-1.055 1.415 1.415 0 011.043-1.043 1.415 1.415 0 001.055-1.644 1.415 1.415 0 01.398-1.374l.02-.02c1-.917 1-2.239 0-3.156l-.026-.026a1.415 1.415 0 01-.398-1.374 1.415 1.415 0 00-1.055-1.644 1.415 1.415 0 01-1.043-1.043 1.415 1.415 0 00-1.644-1.055 1.415 1.415 0 01-1.374-.398l-.02-.02zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd"/>
          </svg>
          <span class="sr-only"><?= e(t('nav.user_menu')) ?></span>
        </summary>
        <div data-dropdown-menu style="z-index: 30;" class="absolute right-0 top-full z-20 mt-1 w-60 rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-800 dark:bg-slate-900">
          <div class="truncate border-b border-slate-100 px-3 py-2 text-xs text-slate-400 dark:border-slate-800 dark:text-slate-500"><?= e($currentUser->displayName()) ?></div>
          <a href="/profil/sicherheit" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.security_2fa')) ?></a>
          <a href="/profil/sitzungen" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.sessions')) ?></a>
          <?php if (!\App\Domain\Technician\TechnicianAccess::isRestricted($currentUser)): ?>
          <a href="/profil/caldav" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.caldav')) ?></a>
          <a href="/profil/carddav" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.carddav')) ?></a>
          <?php endif; ?>
          <a href="/profil/passwort" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.change_password')) ?></a>
          <a href="/profil/darstellung" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.display')) ?></a>
          <a href="/profil/dashboard" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.dashboard_settings')) ?></a>
          <?php if ($isAdmin): ?>
            <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
            <a href="/berichte/einnahmen-ausgaben" class="block px-3 py-2 text-sm <?= $activeNav === 'reports' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800' ?>"><?= e(t('nav.reports')) ?></a>
            <a href="/berichte/umsatzprognose" class="block px-3 py-2 text-sm <?= $activeNav === 'revenue-forecast' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800' ?>"><?= e(t('nav.revenue_forecast')) ?></a>
            <a href="/berichte/abschreibungen" class="block px-3 py-2 text-sm <?= $activeNav === 'depreciation' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800' ?>"><?= e(t('nav.depreciation')) ?></a>
            <a href="/benutzer" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.user_management')) ?></a>
            <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
            <a href="/einstellungen" class="block px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">Alle Einstellungen</a>
            <?php foreach (\App\Infrastructure\Http\SettingsSections::groups() as $navSettingsGroupMobileIcon): ?>
              <a href="<?= e($navSettingsGroupMobileIcon['pages'][0]['href']) ?>" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e($navSettingsGroupMobileIcon['label']) ?></a>
            <?php endforeach; ?>
          <?php endif; ?>
          <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
          <form method="post" action="/logout">
            <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-slate-50 dark:text-red-400 dark:hover:bg-slate-800"><?= \App\Infrastructure\Http\Auth\Session::isImpersonating() ? 'Abmelden (zurück zu Ihrem Konto)' : e(t('nav.logout')) ?></button>
          </form>
        </div>
      </details>
      <?php endif; ?>

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
          <div class="nav-flyout invisible absolute right-0 top-full z-10 rounded-md border border-slate-200 bg-white py-1 opacity-0 shadow-lg transition-opacity duration-100 group-hover:visible group-hover:opacity-100 dark:border-slate-800 dark:bg-slate-900">
            <div class="truncate border-b border-slate-100 px-3 py-2 text-xs text-slate-400 dark:border-slate-800 dark:text-slate-500"><?= e($currentUser->displayName()) ?></div>
            <a href="/profil/sicherheit" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.security_2fa')) ?></a>
            <a href="/profil/sitzungen" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.sessions')) ?></a>
            <?php if (!\App\Domain\Technician\TechnicianAccess::isRestricted($currentUser)): ?>
            <a href="/profil/caldav" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.caldav')) ?></a>
            <a href="/profil/carddav" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.carddav')) ?></a>
            <?php endif; ?>
            <a href="/profil/passwort" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.change_password')) ?></a>
            <a href="/profil/darstellung" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.display')) ?></a>
            <a href="/profil/dashboard" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.dashboard_settings')) ?></a>
            <?php if ($isAdmin): ?>
              <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
              <a href="/berichte/einnahmen-ausgaben" class="block px-3 py-2 text-sm <?= $activeNav === 'reports' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800' ?>"><?= e(t('nav.reports')) ?></a>
              <a href="/berichte/umsatzprognose" class="block px-3 py-2 text-sm <?= $activeNav === 'revenue-forecast' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800' ?>"><?= e(t('nav.revenue_forecast')) ?></a>
              <a href="/berichte/abschreibungen" class="block px-3 py-2 text-sm <?= $activeNav === 'depreciation' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800' ?>"><?= e(t('nav.depreciation')) ?></a>
              <a href="/benutzer" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e(t('nav.user_management')) ?></a>

              <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
              <a href="/einstellungen" class="block px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">Alle Einstellungen</a>
              <?php foreach (\App\Infrastructure\Http\SettingsSections::groups() as $navSettingsGroup): ?>
                <a href="<?= e($navSettingsGroup['pages'][0]['href']) ?>" class="block px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800"><?= e($navSettingsGroup['label']) ?></a>
              <?php endforeach; ?>
            <?php endif; ?>
            <div class="my-1 border-t border-slate-100 dark:border-slate-800"></div>
            <form method="post" action="/logout">
              <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
              <button type="submit" class="block w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-slate-50 dark:text-red-400 dark:hover:bg-slate-800"><?= \App\Infrastructure\Http\Auth\Session::isImpersonating() ? 'Abmelden (zurück zu Ihrem Konto)' : e(t('nav.logout')) ?></button>
            </form>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Mobile-Navigation: unterhalb von lg (1024px) per Hamburger-Button
         ein-/ausklappbar. Da es auf Touch-Geräten kein ":hover" gibt, wird
         die Unterpunkte hier nicht als Hover-Dropdown angezeigt. Standardmäßig
         sind nur die Oberpunkte zu sehen; die Unterpunkte einer Gruppe klappt
         der Pfeil rechts auf (die Gruppe der aktuellen Seite ist offen). -->
    <nav id="mobile-nav" class="hidden border-t border-slate-200 px-2 py-2 lg:hidden dark:border-slate-800">
      <?php if ($canArticles): ?>
      <div data-mobile-group>
        <div class="flex items-center gap-1">
          <a
            href="/artikel"
            class="block min-w-0 flex-1 rounded-md px-3 py-2.5 text-sm font-medium <?= $isArticlesGroupActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
          ><?= e(t('nav.articles')) ?></a>
          <button type="button" data-mobile-group-toggle aria-expanded="<?= $isArticlesGroupActive ? 'true' : 'false' ?>" aria-label="<?= e(t('nav.toggle_subitems')) ?>"
                  class="rounded-md px-3 py-2.5 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="transition: transform .15s;<?= $isArticlesGroupActive ? ' transform: rotate(180deg);' : '' ?>"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
          </button>
        </div>
        <div data-mobile-group-children class="<?= $isArticlesGroupActive ? '' : 'hidden' ?>">
          <?php foreach ($articlesChildren as $child): ?>
            <a
              href="<?= e($child['href']) ?>"
              class="block rounded-md py-2.5 pl-7 pr-3 text-sm <?= $activeNav === $child['key'] ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
            ><?= e($child['label']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($canContacts): ?>
      <div data-mobile-group>
        <div class="flex items-center gap-1">
          <a
            href="/kunden"
            class="block min-w-0 flex-1 rounded-md px-3 py-2.5 text-sm font-medium <?= $isContactsGroupActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
          ><?= e(t('nav.contacts')) ?></a>
          <button type="button" data-mobile-group-toggle aria-expanded="<?= $isContactsGroupActive ? 'true' : 'false' ?>" aria-label="<?= e(t('nav.toggle_subitems')) ?>"
                  class="rounded-md px-3 py-2.5 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="transition: transform .15s;<?= $isContactsGroupActive ? ' transform: rotate(180deg);' : '' ?>"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
          </button>
        </div>
        <div data-mobile-group-children class="<?= $isContactsGroupActive ? '' : 'hidden' ?>">
          <?php foreach ($contactsChildren as $child): ?>
            <a
              href="<?= e($child['href']) ?>"
              class="block rounded-md py-2.5 pl-7 pr-3 text-sm <?= $activeNav === $child['key'] ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
            ><?= e($child['label']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($canProcurement): ?>
      <div data-mobile-group>
        <div class="flex items-center gap-1">
          <a
            href="/belege"
            class="relative block min-w-0 flex-1 rounded-md px-3 py-2.5 text-sm font-medium <?= $isProcurementGroupActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
          ><?= e(t('nav.procurement')) ?>
            <?php if (\App\Infrastructure\Http\ReceiptInboxBadge::pendingCount() > 0): ?>
              <span class="absolute right-3 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full bg-red-500"></span>
            <?php endif; ?>
          </a>
          <button type="button" data-mobile-group-toggle aria-expanded="<?= $isProcurementGroupActive ? 'true' : 'false' ?>" aria-label="<?= e(t('nav.toggle_subitems')) ?>"
                  class="rounded-md px-3 py-2.5 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="transition: transform .15s;<?= $isProcurementGroupActive ? ' transform: rotate(180deg);' : '' ?>"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
          </button>
        </div>
        <div data-mobile-group-children class="<?= $isProcurementGroupActive ? '' : 'hidden' ?>">
          <?php foreach ($procurementChildren as $child): ?>
            <a
              href="<?= e($child['href']) ?>"
              class="relative block rounded-md py-2.5 pl-7 pr-3 text-sm <?= $activeNav === $child['key'] ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
            ><?= e($child['label']) ?>
              <?php if ($child['key'] === 'receipts' && \App\Infrastructure\Http\ReceiptInboxBadge::pendingCount() > 0): ?>
                <span class="absolute right-3 top-1/2 h-2 w-2 -translate-y-1/2 rounded-full bg-red-500"></span>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($canArticles): ?>
      <a
        href="/verkaeufe"
        class="block rounded-md px-3 py-2.5 text-sm font-medium <?= $activeNav === 'sales' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
      ><?= e(t('nav.sales')) ?></a>
      <?php endif; ?>

      <?php if ($canTechnicianRentals): ?>
      <a
        href="/veranstaltungen"
        class="block rounded-md px-3 py-2.5 text-sm font-medium <?= $activeNav === 'rentals' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
      >Meine Veranstaltungen</a>
      <?php endif; ?>

      <?php if ($canInspections): ?>
      <a
        href="/elektropruefung"
        class="block rounded-md px-3 py-2.5 text-sm font-medium <?= $activeNav === 'electrical-inspection' ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
      >Elektroprüfung</a>
      <?php endif; ?>

      <?php if ($canDocuments): ?>
      <div data-mobile-group>
        <div class="flex items-center gap-1">
          <a
            href="/angebote"
            class="block min-w-0 flex-1 rounded-md px-3 py-2.5 text-sm font-medium <?= $isDocumentsGroupActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
          ><?= e(t('nav.documents')) ?></a>
          <button type="button" data-mobile-group-toggle aria-expanded="<?= $isDocumentsGroupActive ? 'true' : 'false' ?>" aria-label="<?= e(t('nav.toggle_subitems')) ?>"
                  class="rounded-md px-3 py-2.5 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="transition: transform .15s;<?= $isDocumentsGroupActive ? ' transform: rotate(180deg);' : '' ?>"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
          </button>
        </div>
        <div data-mobile-group-children class="<?= $isDocumentsGroupActive ? '' : 'hidden' ?>">
          <?php foreach ($documentsChildren as $child): ?>
            <a
              href="<?= e($child['href']) ?>"
              class="block rounded-md py-2.5 pl-7 pr-3 text-sm <?= $activeNav === $child['key'] ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
            ><?= e($child['label']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($canRentals): ?>
      <div data-mobile-group>
        <div class="flex items-center gap-1">
          <a
            href="/veranstaltungen"
            class="block min-w-0 flex-1 rounded-md px-3 py-2.5 text-sm font-medium <?= $isRentalsGroupActive ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
          ><?= e(t('nav.rentals')) ?></a>
          <button type="button" data-mobile-group-toggle aria-expanded="<?= $isRentalsGroupActive ? 'true' : 'false' ?>" aria-label="<?= e(t('nav.toggle_subitems')) ?>"
                  class="rounded-md px-3 py-2.5 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="transition: transform .15s;<?= $isRentalsGroupActive ? ' transform: rotate(180deg);' : '' ?>"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
          </button>
        </div>
        <div data-mobile-group-children class="<?= $isRentalsGroupActive ? '' : 'hidden' ?>">
          <?php foreach ($rentalsChildren as $child): ?>
            <a
              href="<?= e($child['href']) ?>"
              class="block rounded-md py-2.5 pl-7 pr-3 text-sm <?= $activeNav === $child['key'] ? 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-400' : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800' ?>"
            ><?= e($child['label']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
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

      <?php
        // Profil-/Admin-Links (Sicherheit, Sitzungen, CalDAV, Passwort,
        // Darstellung, Abmelden, …) stehen hier bewusst NICHT mehr —
        // dafür gibt es jetzt das eigene mobile Einstellungsmenü über den
        // Zahnrad-Button oben rechts im Header (siehe dortiger Kommentar),
        // damit dieses Hauptmenü auf die eigentliche App-Navigation
        // fokussiert bleibt.
      ?>
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
    <?php $settingsGroupKey = \App\Infrastructure\Http\SettingsSections::groupKeyFor($activeNav); ?>
    <?php if ($settingsGroupKey !== null): ?>
      <?php /* Einstellungsseiten: Navigation links (ab lg), Inhalt rechts — siehe settings/_tabs.php */ ?>
      <div class="lg:grid lg:grid-cols-[14rem_minmax(0,1fr)] lg:items-start lg:gap-10">
      <?php require __DIR__ . '/settings/_tabs.php'; ?>
      <div class="min-w-0">
    <?php endif; ?>
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

    <?= $content ?>
    <?php if ($settingsGroupKey !== null): ?>
      </div>
      </div>
    <?php endif; ?>
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

      function fitNav() {
        if (nav.classList.contains('hidden')) {
          return;
        }
        var top = nav.getBoundingClientRect().top;
        nav.style.maxHeight = Math.max(160, window.innerHeight - Math.max(0, top)) + 'px';
      }
      window.addEventListener('resize', fitNav);

      toggle.addEventListener('click', function () {
        var isOpen = !nav.classList.contains('hidden');
        nav.classList.toggle('hidden', isOpen);
        fitNav();
        iconOpen.classList.toggle('hidden', !isOpen);
        iconClose.classList.toggle('hidden', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
      });
    })();

    // Aufklappbare Gruppen im mobilen Menü (Nutzerwunsch): Pfeil rechts
    // neben einem Oberpunkt blendet dessen Unterpunkte ein/aus.
    (function () {
      document.querySelectorAll('[data-mobile-group-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
          var group = button.closest('[data-mobile-group]');
          var children = group.querySelector('[data-mobile-group-children]');
          var open = children.classList.contains('hidden');
          children.classList.toggle('hidden', !open);
          button.setAttribute('aria-expanded', open ? 'true' : 'false');
          var icon = button.querySelector('svg');
          if (icon) { icon.style.transform = open ? 'rotate(180deg)' : ''; }
        });
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
          .then(function (res) {
            // Fehlerseite nicht in die Liste einsetzen; die Meldung zeigt
            // public/assets/js/load-errors.js.
            if (!res.ok || (res.redirected && new URL(res.url).pathname === '/login')) {
              throw new Error('HTTP ' + res.status);
            }
            return res.text();
          })
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

      var titleEl = document.getElementById('global-confirm-title');
      var iconEl = document.getElementById('global-confirm-icon');
      var DEFAULT_TITLE = titleEl.textContent;
      var DEFAULT_OK = okBtn.textContent;

      // window.confirmModal(message, style, options): options.okLabel benennt
      // den Bestätigen-Button nach der eigentlichen Aktion (z. B.
      // „Veranstaltung löschen“); der Titel wird dann zur Frage
      // („Veranstaltung löschen?“). Ohne Angabe: „Bitte bestätigen“/„Bestätigen“.
      window.confirmModal = function (message, style, options) {
        var okLabel = options && options.okLabel ? String(options.okLabel) : '';
        return new Promise(function (resolve) {
          messageEl.textContent = message;
          titleEl.textContent = okLabel !== '' ? okLabel + '?' : DEFAULT_TITLE;
          okBtn.textContent = okLabel !== '' ? okLabel : DEFAULT_OK;

          var danger = style === 'danger';
          okBtn.classList.remove.apply(okBtn.classList, OK_DEFAULT_CLASSES.concat(OK_DANGER_CLASSES));
          okBtn.classList.add.apply(okBtn.classList, danger ? OK_DANGER_CLASSES : OK_DEFAULT_CLASSES);
          iconEl.className = 'confirm-icon ' + (danger ? 'confirm-icon-danger' : 'confirm-icon-default');
          iconEl.querySelector('[data-icon="default"]').classList.toggle('hidden', danger);
          iconEl.querySelector('[data-icon="danger"]').classList.toggle('hidden', !danger);

          // Offene Menüs (z. B. das „⋯“-Menü, aus dem „Löschen“ kam) schließen,
          // damit sie nicht hinter dem Dialog stehen bleiben.
          document.querySelectorAll('details[open]').forEach(function (d) { d.removeAttribute('open'); });

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
          // Bei gefährlichen Aktionen liegt der Fokus auf „Abbrechen“, damit
          // ein versehentliches Enter nichts löscht.
          (danger ? cancelBtn : okBtn).focus();
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
        // Button-Beschriftung: data-confirm-ok am Formular, sonst der Text
        // des geklickten Absenden-Buttons (sofern kurz genug für einen Button).
        var submitter = e.submitter || form.querySelector('button[type="submit"]');
        var submitterText = submitter ? (submitter.textContent || submitter.value || '').replace(/\s+/g, ' ').trim() : '';
        var okLabel = form.getAttribute('data-confirm-ok') || (submitterText.length > 0 && submitterText.length <= 40 ? submitterText : '');

        window.confirmModal(message, style, { okLabel: okLabel }).then(function (confirmed) {
          if (confirmed) {
            // .submit() statt .requestSubmit(), damit KEIN neues
            // "submit"-Ereignis ausgelöst wird (siehe WHATWG-Spezifikation)
            // — sonst würde dieser Handler sich selbst erneut aufrufen.
            form.submit();
          }
        });
      });

      // Datumsfelder: den im Browser eingebauten Kalender schon beim Klick
      // irgendwo ins Feld öffnen (Chrome/Edge öffnen ihn sonst nur über das
      // kleine Symbol rechts). Tastatureingabe bleibt unverändert möglich;
      // Browser ohne showPicker() behalten einfach ihr normales Verhalten.
      var PICKER_TYPES = ['date', 'datetime-local', 'month', 'week', 'time'];
      document.addEventListener('click', function (e) {
        var input = e.target;
        if (!(input instanceof HTMLInputElement) || PICKER_TYPES.indexOf(input.type) === -1) {
          return;
        }
        if (input.disabled || input.readOnly || typeof input.showPicker !== 'function') {
          return;
        }
        try { input.showPicker(); } catch (err) { /* z. B. in iframes ohne Nutzergeste – dann eben nicht */ }
      });

      // Mitlaufende Aktionszeile langer Formulare (.form-actions, siehe
      // resources/css/app.css): "is-stuck" nur setzen, solange die Zeile
      // wirklich am Fensterrand klebt (dann bekommt sie einen Schatten).
      if ('IntersectionObserver' in window) {
        var stuckObserver = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            entry.target.classList.toggle('is-stuck', entry.intersectionRatio < 1 && entry.boundingClientRect.top > 0);
          });
        }, { threshold: [1], rootMargin: '0px 0px -1px 0px' });
        document.querySelectorAll('.p-6 > .form-actions').forEach(function (bar) { stuckObserver.observe(bar); });
      }

      // [data-mirror-text="<Selektor>"]: zeigt laufend denselben Text wie das
      // erste Element zum Selektor an (z. B. die live berechnete Gesamtsumme
      // in der mitlaufenden Speichern-Leiste von Angebot/AB/Rechnung).
      document.querySelectorAll('[data-mirror-text]').forEach(function (mirror) {
        var source = document.querySelector(mirror.getAttribute('data-mirror-text'));
        if (!source) { return; }
        var sync = function () { mirror.textContent = source.textContent; };
        sync();
        new MutationObserver(sync).observe(source, { childList: true, characterData: true, subtree: true });
      });
      // [data-mirror-label-of="<Selektor>"]: Beschriftung dazu = Text des
      // Elements direkt davor (z. B. <dt>„Gesamtbetrag (brutto)“</dt>).
      document.querySelectorAll('[data-mirror-label-of]').forEach(function (label) {
        var source = document.querySelector(label.getAttribute('data-mirror-label-of'));
        if (source && source.previousElementSibling) { label.textContent = source.previousElementSibling.textContent.trim(); }
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

        // Server-Hinweis respektieren: bei den letzten Zeilen einer Tabelle
        // rendert die PHP-Seite das Menü bereits mit "bottom-full" (siehe
        // z. B. users/_table.php: $openUpward = $index >= count($users) - 2),
        // weil dort unterhalb der Tabelle direkt die Paginierung folgt. Wird
        // hier ausschließlich nach freiem Platz bis zum Fensterrand
        // entschieden, ignoriert das diesen Hinweis — bei kurzen Tabellen
        // ist unterhalb der Paginierung zwar noch jede Menge Platz im
        // Browserfenster frei, das Menü würde dann aber nach unten direkt
        // über die Paginierung hinweg geöffnet. Der Server-Hinweis hat daher
        // Vorrang; die Platzberechnung bleibt als Fallback für Zeilen ohne
        // Hinweis, die trotzdem zu nah am tatsächlichen Fensterrand liegen.
        var serverHintUpward = menu.classList.contains('bottom-full');
        var summaryRect = summary.getBoundingClientRect();
        details._summaryTop = summaryRect.top;
        var menuRect = menu.getBoundingClientRect();
        var spaceBelow = window.innerHeight - summaryRect.bottom;
        var openUpward = serverHintUpward || (spaceBelow < menuRect.height && summaryRect.top > spaceBelow);

        // Vorfahren mit backdrop-filter/filter/transform (z. B. die
        // halbtransparenten Karten bei gesetztem Hintergrundbild) machen
        // sich zum Bezugsrahmen für position:fixed — das Menü würde dann von
        // deren overflow-hidden abgeschnitten (Bug: "3 Punkte"-Menüs in
        // Listen). Solange das Dropdown offen ist, diese Effekte abschalten.
        details._liftedAncestors = [];
        for (var node = details.parentElement; node && node !== document.body; node = node.parentElement) {
          var cs = window.getComputedStyle(node);
          var backdrop = cs.backdropFilter || cs.webkitBackdropFilter || 'none';
          if (backdrop !== 'none' || cs.filter !== 'none' || cs.transform !== 'none' || cs.perspective !== 'none' || (cs.contain && cs.contain.indexOf('paint') !== -1)) {
            details._liftedAncestors.push([node, node.getAttribute('style')]);
            node.style.setProperty('backdrop-filter', 'none', 'important');
            node.style.setProperty('-webkit-backdrop-filter', 'none', 'important');
            node.style.setProperty('filter', 'none', 'important');
            node.style.setProperty('transform', 'none', 'important');
            node.style.setProperty('contain', 'none', 'important');
          }
        }

        menu.style.position = 'fixed';
        // Liegt ein Vorfahre mit filter/backdrop-filter/transform dazwischen
        // (z. B. halbtransparente Karten bei gesetztem Hintergrundbild), ist
        // DER statt des Fensters der Bezugsrahmen für position:fixed — den
        // Versatz einmal messen und herausrechnen.
        menu.style.top = '0px';
        menu.style.left = '0px';
        // Explizit "auto": bei nach oben öffnenden Menüs (Klasse bottom-full,
        // z. B. letzte Tabellenzeilen) würde sonst bottom:100% zusammen mit
        // top unter position:fixed die Höhe auf 0 zusammendrücken — die
        // Einträge lagen dann ohne Hintergrund über der Tabelle (Bugreport
        // "Menü bei den Benutzern sieht komisch aus").
        menu.style.bottom = 'auto';
        menu.style.right = 'auto';
        menu.style.marginTop = '0px';
        menu.style.marginBottom = '0px';
        var origin = menu.getBoundingClientRect();
        var originX = origin.left;
        var originY = origin.top;
        if (openUpward) {
          menu.style.top = Math.max(8, summaryRect.top - 4 - menuRect.height) - originY + 'px';
        } else {
          menu.style.top = (summaryRect.bottom + 4 - originY) + 'px';
        }

        // Lange Menüs (z. B. das mobile Einstellungsmenü) scrollbar halten,
        // statt sie über den Bildschirmrand hinaus laufen zu lassen.
        var available = openUpward ? summaryRect.top - 12 : window.innerHeight - summaryRect.bottom - 12;
        if (menuRect.height > available) {
          menu.style.maxHeight = Math.max(120, available) + 'px';
          menu.style.overflowY = 'auto';
          menu.style.overscrollBehavior = 'contain';
        }

        var left = summaryRect.right - menuRect.width;
        left = Math.max(8, Math.min(left, window.innerWidth - menuRect.width - 8));
        menu.style.left = (left - originX) + 'px';
        menu.style.right = 'auto';
      }

      function resetDropdownMenuPosition(details) {
        (details._liftedAncestors || []).forEach(function (entry) {
          if (entry[1] === null) { entry[0].removeAttribute('style'); } else { entry[0].setAttribute('style', entry[1]); }
        });
        details._liftedAncestors = [];
        var menu = details.querySelector('[data-dropdown-menu]');
        if (!menu) {
          return;
        }
        menu.style.position = '';
        menu.style.marginTop = '';
        menu.style.marginBottom = '';
        menu.style.maxHeight = '';
        menu.style.overflowY = '';
        menu.style.overscrollBehavior = '';
        menu.style.top = '';
        menu.style.bottom = '';
        menu.style.left = '';
        menu.style.right = '';
      }

      // Ein Listener in der Capture-Phase ("toggle" blubbert nicht), damit
      // auch später eingefügte Dropdowns (z. B. per JS nachgeladene Zeilen)
      // korrekt positioniert werden.
      // Übrige <details>-Aufklapper mit absolut positioniertem Inhalt (z. B.
      // „Per E-Mail senden“-Formulare, „Route zum Veranstaltungsort“):
      // bleiben absolut positioniert (Formulare mit Eingabefeldern sollen
      // beim Scrollen nicht zugehen), bekommen aber ebenfalls die
      // Vorfahren-Effekte abgeschaltet und einen hohen z-index — sonst
      // liegen sie bei gesetztem Hintergrundbild hinter der nächsten Karte.
      function liftAncestors(details) {
        details._liftedAncestors = details._liftedAncestors || [];
        for (var node = details.parentElement; node && node !== document.body; node = node.parentElement) {
          var cs = window.getComputedStyle(node);
          var backdrop = cs.backdropFilter || cs.webkitBackdropFilter || 'none';
          if (backdrop !== 'none' || cs.filter !== 'none' || cs.transform !== 'none' || (cs.contain && cs.contain.indexOf('paint') !== -1)) {
            details._liftedAncestors.push([node, node.getAttribute('style')]);
            node.style.setProperty('backdrop-filter', 'none', 'important');
            node.style.setProperty('-webkit-backdrop-filter', 'none', 'important');
            node.style.setProperty('filter', 'none', 'important');
            node.style.setProperty('transform', 'none', 'important');
            node.style.setProperty('contain', 'none', 'important');
          }
        }
      }
      function restoreAncestors(details) {
        (details._liftedAncestors || []).forEach(function (entry) {
          if (entry[1] === null) { entry[0].removeAttribute('style'); } else { entry[0].setAttribute('style', entry[1]); }
        });
        details._liftedAncestors = [];
      }
      function popoverOf(details) {
        for (var i = 0; i < details.children.length; i++) {
          var child = details.children[i];
          if (child.tagName !== 'SUMMARY' && window.getComputedStyle(child).position === 'absolute') {
            return child;
          }
        }
        return null;
      }

      document.addEventListener('toggle', function (event) {
        var details = event.target;
        if (!(details instanceof HTMLDetailsElement)) {
          return;
        }
        if (!details.hasAttribute('data-dropdown')) {
          var popover = details.open ? popoverOf(details) : details._popover;
          if (!popover) {
            return;
          }
          if (details.open) {
            details._popover = popover;
            details._popoverZ = popover.style.zIndex;
            popover.style.zIndex = '60';
            liftAncestors(details);
            // Unten aus dem Fenster ragend: Seite so weit scrollen, dass das Panel sichtbar ist.
            var rect = popover.getBoundingClientRect();
            if (rect.bottom > window.innerHeight) { popover.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
          } else {
            popover.style.zIndex = details._popoverZ || '';
            restoreAncestors(details);
          }
          return;
        }
        if (details.open) {
          positionDropdownMenu(details);
        } else {
          resetDropdownMenuPosition(details);
        }
      }, true);

      // Scrollt die Seite (oder der Tabellen-Wrapper) während ein Dropdown
      // offen ist, würde die per position:fixed berechnete Position sonst
      // stehen bleiben und nicht mehr zum Auslöser passen — einfacher und
      // robuster als laufend neu zu positionieren: das Dropdown wird
      // stattdessen einfach geschlossen (capture:true, da der Scroll des
      // .overflow-x-auto-Wrappers selbst nicht bis zum document bubbelt).
      // Ausnahmen (Bug "Einstellungsmenü verschwindet beim Scrollen"):
      // Scrollen INNERHALB eines langen Menüs (z. B. das mobile
      // Einstellungsmenü mit max-height + Scrollbalken) schließt nichts, und
      // Menüs, deren Auslöser sich beim Scrollen nicht bewegt (sticky
      // Kopfzeile), bleiben ebenfalls offen.
      document.addEventListener('scroll', function (event) {
        var target = event.target;
        document.querySelectorAll('details[data-dropdown][open]').forEach(function (details) {
          var menu = details.querySelector('[data-dropdown-menu]');
          if (menu && target instanceof Node && menu.contains(target)) {
            return;
          }
          var summary = details.querySelector('summary');
          if (summary && typeof details._summaryTop === 'number' && Math.abs(summary.getBoundingClientRect().top - details._summaryTop) < 2) {
            return;
          }
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
  <!-- Live-Suche (Autocomplete) für Datensatz-Auswahlfelder, siehe
       public/assets/js/live-search.js und
       App\Infrastructure\Http\renderLiveSearchField() — global geladen
       (statt pro View), da die Komponente auf vielen, unterschiedlichen
       Seiten verwendet wird. -->
  <script src="/assets/js/live-search.js" defer></script>
  <!-- Artikel-Mehrfachauswahl-Modal, siehe public/assets/js/article-picker.js
       und App\Infrastructure\Http\renderArticlePickerModal() — global
       geladen aus demselben Grund wie live-search.js direkt darüber. -->
  <script src="/assets/js/article-picker.js" defer></script>
  <script src="/assets/js/dry-hire.js" defer></script>
  <!-- Set-Auswahl in Angebot/AB/Rechnung (Migration 113), siehe public/assets/js/set-picker.js -->
  <script src="/assets/js/set-picker.js" defer></script>
</body>
</html>
