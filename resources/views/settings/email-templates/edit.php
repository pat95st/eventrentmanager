<?php
/** @var \App\Domain\Mail\EmailTemplateKey $key */
/** @var \App\Domain\Mail\EmailTemplate $template */
/** @var array<string, string> $errors */
/** @var 'none'|'svg'|'available' $logoState Siehe EmailTemplateSettingsController::logoState(). */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;
?>

<div class="mb-6">
    <a href="/einstellungen/email-vorlagen" class="text-xs text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">&larr; <?= e(t('settings.email_templates.back_to_list')) ?></a>
    <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($key->label()) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e($key->description()) ?>
    </p>
</div>

<div class="grid max-w-4xl grid-cols-1 gap-6">
<form method="post" action="/einstellungen/email-vorlagen/<?= e($key->value) ?>" class="space-y-5 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <div>
        <label for="subject" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.email_templates.field.subject')) ?></label>
        <input type="text" id="subject" name="subject"
               value="<?= e($template->subject) ?>"
               class="mt-1 w-full rounded-md border <?= isset($errors['subject']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
        <?php if (isset($errors['subject'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['subject']) ?></p><?php endif; ?>
    </div>

    <div>
        <p class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.email_templates.field.variables')) ?></p>
        <div class="flex flex-wrap gap-1.5">
            <button type="button" data-insert-variable="company_name" class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-left text-xs hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700" title="<?= e(t('settings.email_templates.variable.company_name')) ?>">
                <code class="font-mono text-[11px] text-brand-700 dark:text-brand-400">{{company_name}}</code>
            </button>
            <?php foreach ($key->availableVariables() as $name => $description): ?>
                <button type="button" data-insert-variable="<?= e($name) ?>" class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-2 py-1 text-left text-xs hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700" title="<?= e($description) ?>">
                    <code class="font-mono text-[11px] text-brand-700 dark:text-brand-400">{{<?= e($name) ?>}}</code>
                </button>
            <?php endforeach; ?>
        </div>
        <p class="mt-1.5 text-[11px] text-slate-400 dark:text-slate-500"><?= e(t('settings.email_templates.variables_hint')) ?></p>
    </div>

    <div>
        <p class="mb-2 block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.email_templates.field.logo')) ?></p>
        <button
            type="button"
            data-insert-logo
            <?= $logoState !== 'available' ? 'disabled' : '' ?>
            class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:disabled:hover:bg-slate-800"
        >
            <?= e(t('settings.email_templates.field.logo_button')) ?>
        </button>
        <?php if ($logoState === 'none'): ?>
            <p class="mt-1.5 text-[11px] text-amber-600 dark:text-amber-400">
                <?= t('settings.email_templates.logo_hint_none', ['link' => '<a href="/einstellungen/firma" class="underline">' . e(t('settings.email_templates.logo_hint_link')) . '</a>']) ?>
            </p>
        <?php elseif ($logoState === 'svg'): ?>
            <p class="mt-1.5 text-[11px] text-amber-600 dark:text-amber-400">
                <?= t('settings.email_templates.logo_hint_svg', ['link' => '<a href="/einstellungen/firma" class="underline">' . e(t('settings.email_templates.logo_hint_link')) . '</a>']) ?>
            </p>
        <?php else: ?>
            <p class="mt-1.5 text-[11px] text-slate-400 dark:text-slate-500"><?= e(t('settings.email_templates.logo_hint_available')) ?></p>
        <?php endif; ?>
    </div>

    <div>
        <label class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.email_templates.field.body')) ?></label>

        <div data-html-field class="html-field mt-1">
            <div data-html-toolbar class="mb-0 hidden flex-wrap gap-1 rounded-t-md border border-b-0 border-slate-300 bg-slate-50 p-1.5 dark:border-slate-700 dark:bg-slate-800">
                <button type="button" data-cmd="bold" title="<?= e(t('settings.legal.markdown.toolbar.bold')) ?>" class="rounded px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">F</button>
                <button type="button" data-cmd="italic" title="<?= e(t('settings.legal.markdown.toolbar.italic')) ?>" class="rounded px-2 py-1 text-xs italic text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700">K</button>
                <select data-heading-select title="<?= e(t('settings.legal.markdown.toolbar.heading')) ?>" class="rounded border border-slate-300 bg-white px-1.5 py-1 text-xs text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                    <option value=""><?= e(t('settings.legal.markdown.toolbar.heading_placeholder')) ?></option>
                    <option value="H1"><?= e(t('settings.legal.markdown.toolbar.heading1')) ?></option>
                    <option value="H2"><?= e(t('settings.legal.markdown.toolbar.heading2')) ?></option>
                    <option value="H3"><?= e(t('settings.legal.markdown.toolbar.heading3')) ?></option>
                    <option value="P"><?= e(t('settings.legal.markdown.toolbar.paragraph')) ?></option>
                </select>
                <button type="button" data-cmd="insertUnorderedList" title="<?= e(t('settings.legal.markdown.toolbar.bullet_list')) ?>" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"><?= e(t('settings.legal.markdown.toolbar.bullet_list')) ?></button>
                <button type="button" data-cmd="insertOrderedList" title="<?= e(t('settings.legal.markdown.toolbar.numbered_list')) ?>" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"><?= e(t('settings.legal.markdown.toolbar.numbered_list')) ?></button>
                <button type="button" data-cmd="link" title="<?= e(t('settings.legal.markdown.toolbar.link')) ?>" class="rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"><?= e(t('settings.legal.markdown.toolbar.link')) ?></button>
            </div>
            <div
                data-html-editable
                contenteditable="true"
                class="hidden min-h-[14rem] rounded-b-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950"
            ></div>
            <textarea
                data-html-source
                name="body_html"
                rows="12"
                class="w-full rounded-md border <?= isset($errors['bodyHtml']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 font-mono text-xs focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
            ><?= e($template->bodyHtml) ?></textarea>
            <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500"><?= e(t('settings.email_templates.body_hint')) ?></p>
        </div>
        <?php if (isset($errors['bodyHtml'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['bodyHtml']) ?></p><?php endif; ?>
    </div>

    <div class="flex items-center gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>

<form method="post" action="/einstellungen/email-vorlagen/<?= e($key->value) ?>/zuruecksetzen" data-confirm="<?= e(t('settings.email_templates.confirm_reset')) ?>" data-confirm-style="danger">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
    <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
        <?= e(t('settings.email_templates.reset')) ?>
    </button>
</form>
</div>

<script>
  // Einfacher WYSIWYG-Editor auf Basis von contenteditable + execCommand für
  // den HTML-Mail-Inhalt. Im Unterschied zu den Markdown-Feldern (siehe
  // settings/legal.php) ist das Speicherformat hier direkt HTML — kein
  // Hin-/Rückkonvertieren nötig, die versteckte Textarea wird 1:1 mit
  // editable.innerHTML synchron gehalten.
  (function () {
    document.querySelectorAll('[data-html-field]').forEach(function (field) {
      var toolbar = field.querySelector('[data-html-toolbar]');
      var editable = field.querySelector('[data-html-editable]');
      var textarea = field.querySelector('[data-html-source]');

      if (!toolbar || !editable || !textarea) {
        return;
      }

      editable.innerHTML = textarea.value;
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
          var cmd = btn.getAttribute('data-cmd');
          if (cmd === 'link') {
            var url = window.prompt('Link-URL eingeben:', 'https://');
            if (url) {
              document.execCommand('createLink', false, url);
            }
          } else {
            document.execCommand(cmd, false, undefined);
          }
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

      var form = field.closest('form');
      if (form) {
        form.addEventListener('submit', sync);
      }

      // Platzhalter-Chips: fügen den jeweiligen Platzhalter an der aktuellen
      // Cursor-Position im Editor ein.
      document.querySelectorAll('[data-insert-variable]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          editable.focus();
          document.execCommand('insertText', false, '{{' + btn.getAttribute('data-insert-variable') + '}}');
          sync();
        });
      });

      // "Firmenlogo einfügen": fügt ein <img>-Tag mit einer "cid:"-Referenz
      // ein (statt einer öffentlichen URL — das Logo liegt bewusst NICHT
      // öffentlich unter public/, siehe LocalFileStorage). Beim Versand löst
      // EmailTemplateRenderer::buildLogoAttachment() diese Referenz auf einen
      // inline eingebetteten Bildanhang auf (siehe SmtpMailer). Die
      // Schaltfläche ist deaktiviert, solange kein einbettbares (Nicht-SVG-)
      // Logo hinterlegt ist (siehe EmailTemplateSettingsController::logoState()).
      var logoButton = document.querySelector('[data-insert-logo]');
      if (logoButton && !logoButton.disabled) {
        logoButton.addEventListener('click', function () {
          editable.focus();
          document.execCommand('insertHTML', false, '<img src="cid:company-logo" alt="Logo" style="max-height:60px;">');
          sync();
        });
      }
    });
  })();
</script>
