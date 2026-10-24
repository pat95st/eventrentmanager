<?php
/** @var \App\Domain\Settings\InvoicePdfSettings $settings */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $key, ?string $fallback) => array_key_exists($key, $old) ? (string) $old[$key] : ($fallback ?? '');

/**
 * Rendert ein Rich-Text-Editorfeld: eine <textarea> mit dem tatsächlich
 * abgesendeten Formularfeld (rohes, bereits serverseitig bereinigtes
 * HTML-Fragment, siehe App\Infrastructure\RichText\HtmlSanitizer) plus einer
 * per JS darüber gelegten, WYSIWYG-artigen contenteditable-Oberfläche mit
 * Toolbar (siehe Skript am Ende der Datei). Ohne JavaScript bleibt nur die
 * Textarea sichtbar und bedienbar — dort dann als rohes HTML statt als
 * Markdown (progressive enhancement, aber der Admin bearbeitet in diesem
 * Fall direkt Markup statt Text). Identisch zu
 * resources/views/settings/offer-pdf-template.php (bewusst dupliziert,
 * siehe dortiger Kommentar — kein gemeinsames Include, um dieses etablierte
 * Muster nicht anzufassen).
 */
if (!function_exists('renderInvoicePdfRichTextField')) {
    function renderInvoicePdfRichTextField(string $name, string $value, ?string $error): void
    {
        ?>
    <div data-rt-field class="rt-field">
        <div data-rt-toolbar class="mb-0 hidden flex-wrap gap-1 rounded-t-md border border-b-0 border-slate-300 bg-slate-50 p-1.5 dark:border-slate-700 dark:bg-slate-800">
            <button type="button" data-cmd="bold" title="<?= e(t('rich_text.toolbar.bold')) ?>" class="rounded px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">F</button>
            <button type="button" data-cmd="italic" title="<?= e(t('rich_text.toolbar.italic')) ?>" class="rounded px-2 py-1 text-xs italic text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">K</button>
            <button type="button" data-cmd="underline" title="<?= e(t('rich_text.toolbar.underline')) ?>" class="rounded px-2 py-1 text-xs underline text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">U</button>
            <select data-heading-select title="<?= e(t('rich_text.toolbar.heading')) ?>" class="rounded border border-slate-300 bg-white px-1.5 py-1 text-xs text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <option value=""><?= e(t('rich_text.toolbar.heading_placeholder')) ?></option>
                <option value="H1"><?= e(t('rich_text.toolbar.heading1')) ?></option>
                <option value="H2"><?= e(t('rich_text.toolbar.heading2')) ?></option>
                <option value="H3"><?= e(t('rich_text.toolbar.heading3')) ?></option>
                <option value="P"><?= e(t('rich_text.toolbar.paragraph')) ?></option>
            </select>
            <button type="button" data-cmd="insertUnorderedList" title="<?= e(t('rich_text.toolbar.bullet_list')) ?>" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"><?= e(t('rich_text.toolbar.bullet_list')) ?></button>
            <button type="button" data-cmd="insertOrderedList" title="<?= e(t('rich_text.toolbar.numbered_list')) ?>" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"><?= e(t('rich_text.toolbar.numbered_list')) ?></button>
            <span class="mx-0.5 w-px self-stretch bg-slate-300 dark:bg-slate-600"></span>
            <button type="button" data-cmd="justifyLeft" title="<?= e(t('rich_text.toolbar.align_left')) ?>" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">⇤</button>
            <button type="button" data-cmd="justifyCenter" title="<?= e(t('rich_text.toolbar.align_center')) ?>" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">⇔</button>
            <button type="button" data-cmd="justifyRight" title="<?= e(t('rich_text.toolbar.align_right')) ?>" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">⇥</button>
        </div>
        <div
            data-rt-editable
            contenteditable="true"
            class="hidden min-h-[8rem] rounded-b-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950"
        ></div>
        <textarea
            data-rt-source
            name="<?= e($name) ?>"
            rows="6"
            class="w-full rounded-md border <?= $error !== null ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
        ><?= e($value) ?></textarea>
        <?php if ($error !== null): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($error) ?></p><?php endif; ?>
    </div>
        <?php
    }
}
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.invoice_pdf_template.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e(t('settings.invoice_pdf_template.subtitle')) ?>
    </p>
</div>

<form method="post" action="/einstellungen/rechnungsvorlage" class="max-w-3xl space-y-8">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <!-- Einleitungstext: erscheint im PDF vor der Positionstabelle. -->
    <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.invoice_pdf_template.intro.title')) ?></h2>
            <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.invoice_pdf_template.intro.hint')) ?></p>
        </div>
        <?php renderInvoicePdfRichTextField('intro_html', $val('intro_html', $settings->introHtml), $errors['introHtml'] ?? null); ?>
    </div>

    <!-- Schlusstext: erscheint im PDF nach der Summenzeile. -->
    <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.invoice_pdf_template.closing.title')) ?></h2>
            <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.invoice_pdf_template.closing.hint')) ?></p>
        </div>
        <?php renderInvoicePdfRichTextField('closing_html', $val('closing_html', $settings->closingHtml), $errors['closingHtml'] ?? null); ?>
    </div>

    <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.invoice_pdf_template.rich_text_hint')) ?></p>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>

<script>
  // WYSIWYG-Editor auf Basis von contenteditable + execCommand, dessen
  // Speicherformat direkt HTML ist. Identisch zum Skript in
  // resources/views/settings/offer-pdf-template.php (bewusst dupliziert,
  // siehe dortiger Kommentar).
  (function () {
    try {
      document.execCommand('defaultParagraphSeparator', false, 'p');
    } catch (e) { /* ältere Browser: einfach ignorieren, <div> wird ebenfalls verstanden */ }

    document.querySelectorAll('[data-rt-field]').forEach(function (field) {
      var toolbar = field.querySelector('[data-rt-toolbar]');
      var editable = field.querySelector('[data-rt-editable]');
      var textarea = field.querySelector('[data-rt-source]');

      if (!toolbar || !editable || !textarea) {
        return;
      }

      editable.innerHTML = textarea.value || '<p></p>';
      toolbar.classList.remove('hidden');
      toolbar.classList.add('flex');
      editable.classList.remove('hidden');
      textarea.classList.add('hidden');

      function sync() {
        textarea.value = editable.innerHTML;
      }

      editable.addEventListener('input', sync);
      editable.addEventListener('blur', sync);

      toolbar.querySelectorAll('[data-cmd]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          editable.focus();
          document.execCommand(btn.getAttribute('data-cmd'), false, undefined);
          sync();
        });
      });

      var headingSelect = toolbar.querySelector('[data-heading-select]');
      if (headingSelect) {
        headingSelect.addEventListener('change', function () {
          editable.focus();
          if (headingSelect.value) {
            document.execCommand('formatBlock', false, headingSelect.value);
          }
          headingSelect.value = '';
          sync();
        });
      }
    });

    var form = document.querySelector('form[action="/einstellungen/rechnungsvorlage"]');
    if (form) {
      form.addEventListener('submit', function () {
        document.querySelectorAll('[data-rt-field]').forEach(function (field) {
          var editable = field.querySelector('[data-rt-editable]');
          var textarea = field.querySelector('[data-rt-source]');
          if (editable && textarea && !editable.classList.contains('hidden')) {
            textarea.value = editable.innerHTML;
          }
        });
      });
    }
  })();
</script>
