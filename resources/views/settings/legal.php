<?php
/** @var \App\Domain\Settings\LegalSettings $settings */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$val = fn (string $key, ?string $fallback) => array_key_exists($key, $old) ? (string) $old[$key] : ($fallback ?? '');

/**
 * Rendert ein Markdown-Editor-Feld: eine <textarea> mit dem eigentlichen
 * Markdown-Wert (das tatsächlich abgesendete Formularfeld, funktioniert
 * auch ganz ohne JavaScript) plus einer per JS darüber gelegten,
 * WYSIWYG-artigen contenteditable-Oberfläche mit Toolbar (siehe Skript am
 * Ende der Datei). Ohne JavaScript bleibt nur die Textarea sichtbar und
 * bedienbar (progressive enhancement).
 */
if (!function_exists('renderMarkdownField')) {
    function renderMarkdownField(string $name, string $value, ?string $error): void
    {
        ?>
    <div data-md-field class="md-field">
        <div data-md-toolbar class="mb-0 hidden flex-wrap gap-1 rounded-t-md border border-b-0 border-slate-300 bg-slate-50 p-1.5 dark:border-slate-700 dark:bg-slate-800">
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
            data-md-editable
            contenteditable="true"
            class="hidden min-h-[10rem] rounded-b-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950"
        ></div>
        <textarea
            data-md-source
            name="<?= e($name) ?>"
            rows="8"
            class="w-full rounded-md border <?= $error !== null ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
        ><?= e($value) ?></textarea>
        <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">
            <?= e(t('settings.legal.markdown.hint')) ?>
        </p>
        <?php if ($error !== null): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($error) ?></p><?php endif; ?>
    </div>
        <?php
    }
}
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.legal.title')) ?></h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">
        <?= e(t('settings.legal.subtitle')) ?>
    </p>
</div>

<form method="post" action="/einstellungen/rechtliches" class="max-w-3xl space-y-8">
  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">

    <!-- Vermietbedingungen: ausschließlich Markdown-Text. -->
    <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.legal.rental_terms.title')) ?></h2>
            <p class="text-xs text-slate-400 dark:text-slate-500"><?= e(t('settings.legal.rental_terms.hint')) ?></p>
        </div>
        <?php renderMarkdownField('rental_terms_markdown', $val('rental_terms_markdown', $settings->rentalTermsMarkdown), $errors['rentalTermsMarkdown'] ?? null); ?>
    </div>

    <!-- Impressum: Modus-Umschalter Text (Markdown) / Externer Link (URL). -->
    <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.legal.imprint.title')) ?></h2>
        </div>

        <div data-mode-field="imprint" class="space-y-3">
            <div class="flex gap-4 text-sm text-slate-600 dark:text-slate-400">
                <label class="flex items-center gap-1.5">
                    <input type="radio" name="imprint_mode" value="markdown" data-mode-radio
                           <?= $val('imprint_mode', $settings->imprintMode) !== 'link' ? 'checked' : '' ?>>
                    <?= e(t('settings.legal.mode.text')) ?>
                </label>
                <label class="flex items-center gap-1.5">
                    <input type="radio" name="imprint_mode" value="link" data-mode-radio
                           <?= $val('imprint_mode', $settings->imprintMode) === 'link' ? 'checked' : '' ?>>
                    <?= e(t('settings.legal.mode.link')) ?>
                </label>
            </div>

            <div data-mode-panel="markdown">
                <?php renderMarkdownField('imprint_markdown', $val('imprint_markdown', $settings->imprintMarkdown), $errors['imprintMarkdown'] ?? null); ?>
            </div>

            <div data-mode-panel="link">
                <label for="imprint_url" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.legal.field.url')) ?></label>
                <input type="url" id="imprint_url" name="imprint_url" placeholder="https://beispiel.de/impressum"
                       value="<?= e($val('imprint_url', $settings->imprintUrl)) ?>"
                       class="mt-1 w-full rounded-md border <?= isset($errors['imprintUrl']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <?php if (isset($errors['imprintUrl'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['imprintUrl']) ?></p><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Datenschutzerklärung: Modus-Umschalter Text (Markdown) / Externer Link (URL). -->
    <div class="space-y-3 rounded-lg border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div>
            <h2 class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?= e(t('settings.legal.privacy.title')) ?></h2>
        </div>

        <div data-mode-field="privacy" class="space-y-3">
            <div class="flex gap-4 text-sm text-slate-600 dark:text-slate-400">
                <label class="flex items-center gap-1.5">
                    <input type="radio" name="privacy_mode" value="markdown" data-mode-radio
                           <?= $val('privacy_mode', $settings->privacyMode) !== 'link' ? 'checked' : '' ?>>
                    <?= e(t('settings.legal.mode.text')) ?>
                </label>
                <label class="flex items-center gap-1.5">
                    <input type="radio" name="privacy_mode" value="link" data-mode-radio
                           <?= $val('privacy_mode', $settings->privacyMode) === 'link' ? 'checked' : '' ?>>
                    <?= e(t('settings.legal.mode.link')) ?>
                </label>
            </div>

            <div data-mode-panel="markdown">
                <?php renderMarkdownField('privacy_markdown', $val('privacy_markdown', $settings->privacyMarkdown), $errors['privacyMarkdown'] ?? null); ?>
            </div>

            <div data-mode-panel="link">
                <label for="privacy_url" class="block text-xs font-medium text-slate-600 dark:text-slate-400"><?= e(t('settings.legal.field.url')) ?></label>
                <input type="url" id="privacy_url" name="privacy_url" placeholder="https://beispiel.de/datenschutz"
                       value="<?= e($val('privacy_url', $settings->privacyUrl)) ?>"
                       class="mt-1 w-full rounded-md border <?= isset($errors['privacyUrl']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500">
                <?php if (isset($errors['privacyUrl'])): ?><p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['privacyUrl']) ?></p><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">
            <?= e(t('common.save')) ?>
        </button>
    </div>
</form>

<script>
  // Modus-Umschalter (Text/Externer Link): blendet je Feld (Impressum,
  // Datenschutzerklärung) den passenden Bereich ein/aus. Funktioniert rein
  // clientseitig als Komfortfunktion — ohne JavaScript sind beide Bereiche
  // sichtbar, der Server wertet aber ohnehin nur den per Radio-Button
  // gewählten Modus aus.
  (function () {
    document.querySelectorAll('[data-mode-field]').forEach(function (field) {
      var radios = field.querySelectorAll('[data-mode-radio]');
      var panels = {
        markdown: field.querySelector('[data-mode-panel="markdown"]'),
        link: field.querySelector('[data-mode-panel="link"]'),
      };

      function sync() {
        var checked = field.querySelector('[data-mode-radio]:checked');
        var mode = checked ? checked.value : 'markdown';
        panels.markdown.classList.toggle('hidden', mode !== 'markdown');
        panels.link.classList.toggle('hidden', mode !== 'link');
      }

      radios.forEach(function (radio) {
        radio.addEventListener('change', sync);
      });

      sync();
    });
  })();

  // Einfacher WYSIWYG-Editor auf Basis von contenteditable + execCommand,
  // dessen Speicherformat immer Markdown bleibt (siehe versteckte Textarea
  // je Feld). Ohne externe Bibliotheken umgesetzt.
  (function () {
    function escapeHtml(s) {
      return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // Markdown -> HTML, zum initialen Befüllen des contenteditable-Bereichs.
    // Deckt bewusst dieselbe Teilmenge wie der serverseitige
    // MarkdownRenderer (src/Infrastructure/Http/MarkdownRenderer.php) ab.
    function markdownToHtml(md) {
      md = (md || '').replace(/\r\n/g, '\n');
      var lines = md.split('\n');
      var html = '';
      var para = [];
      var listType = null;
      var items = [];

      function inline(text) {
        var t = escapeHtml(text);
        t = t.replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, function (m, label, url) {
          if (/^(https?:\/\/|mailto:)/i.test(url)) {
            return '<a href="' + escapeHtml(url) + '">' + label + '</a>';
          }
          return label;
        });
        t = t.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        t = t.replace(/(^|[^*])\*([^*]+)\*(?!\*)/g, '$1<em>$2</em>');
        return t;
      }

      function flushPara() {
        if (para.length) {
          html += '<p>' + para.join(' ') + '</p>';
          para = [];
        }
      }

      function flushList() {
        if (listType) {
          html += '<' + listType + '>' + items.map(function (i) { return '<li>' + i + '</li>'; }).join('') + '</' + listType + '>';
          listType = null;
          items = [];
        }
      }

      lines.forEach(function (raw) {
        var line = raw.trim();
        if (line === '') { flushPara(); flushList(); return; }

        var h = line.match(/^(#{1,3})\s+(.*)$/);
        if (h) { flushPara(); flushList(); var lvl = h[1].length; html += '<h' + lvl + '>' + inline(h[2]) + '</h' + lvl + '>'; return; }

        var ul = line.match(/^[-*]\s+(.*)$/);
        if (ul) { flushPara(); if (listType && listType !== 'ul') flushList(); listType = 'ul'; items.push(inline(ul[1])); return; }

        var ol = line.match(/^\d+\.\s+(.*)$/);
        if (ol) { flushPara(); if (listType && listType !== 'ol') flushList(); listType = 'ol'; items.push(inline(ol[1])); return; }

        flushList();
        para.push(inline(line));
      });

      flushPara();
      flushList();

      return html || '<p></p>';
    }

    // HTML (aus dem contenteditable-Bereich) -> Markdown, vor dem Absenden
    // des Formulars in die tatsächliche Textarea geschrieben.
    function htmlToMarkdown(rootEl) {
      function walk(node) {
        if (node.nodeType === Node.TEXT_NODE) {
          return node.textContent;
        }
        if (node.nodeType !== Node.ELEMENT_NODE) {
          return '';
        }

        var tag = node.tagName.toLowerCase();
        var children = Array.prototype.map.call(node.childNodes, walk).join('');

        switch (tag) {
          case 'strong':
          case 'b':
            return '**' + children + '**';
          case 'em':
          case 'i':
            return '*' + children + '*';
          case 'h1':
            return '\n# ' + children.trim() + '\n\n';
          case 'h2':
            return '\n## ' + children.trim() + '\n\n';
          case 'h3':
            return '\n### ' + children.trim() + '\n\n';
          case 'a':
            var href = node.getAttribute('href') || '';
            return children.trim() === '' ? '' : '[' + children + '](' + href + ')';
          case 'br':
            return '\n';
          case 'p':
            return children.trim() === '' ? '' : children.trim() + '\n\n';
          case 'div':
            return children + '\n';
          case 'ul':
            return '\n' + Array.prototype.map.call(node.children, function (li) {
              return '- ' + walk(li).trim();
            }).join('\n') + '\n\n';
          case 'ol':
            return '\n' + Array.prototype.map.call(node.children, function (li, i) {
              return (i + 1) + '. ' + walk(li).trim();
            }).join('\n') + '\n\n';
          case 'li':
            return children;
          default:
            return children;
        }
      }

      var md = Array.prototype.map.call(rootEl.childNodes, walk).join('');

      return md.replace(/\n{3,}/g, '\n\n').trim();
    }

    document.querySelectorAll('[data-md-field]').forEach(function (field) {
      var toolbar = field.querySelector('[data-md-toolbar]');
      var editable = field.querySelector('[data-md-editable]');
      var textarea = field.querySelector('[data-md-source]');

      if (!toolbar || !editable || !textarea) {
        return;
      }

      // JS ist verfügbar: Textarea optisch durch den WYSIWYG-Editor
      // ersetzen (bleibt im DOM als tatsächliches, abgesendetes Formularfeld
      // erhalten, wird aber ausgeblendet).
      editable.innerHTML = markdownToHtml(textarea.value);
      toolbar.classList.remove('hidden');
      toolbar.classList.add('flex');
      editable.classList.remove('hidden');
      textarea.classList.add('hidden');

      function sync() {
        textarea.value = htmlToMarkdown(editable);
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
    });

    // Vor dem Absenden alle Editoren noch einmal explizit synchronisieren
    // (z. B. falls der letzte Tastendruck kein "input"-Event mehr ausgelöst hat).
    var form = document.querySelector('form[action="/einstellungen/rechtliches"]');
    if (form) {
      form.addEventListener('submit', function () {
        document.querySelectorAll('[data-md-field]').forEach(function (field) {
          var editable = field.querySelector('[data-md-editable]');
          var textarea = field.querySelector('[data-md-source]');
          if (editable && textarea && !editable.classList.contains('hidden')) {
            textarea.value = htmlToMarkdown(editable);
          }
        });
      });
    }
  })();
</script>
