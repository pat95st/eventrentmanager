<?php
/** @var \App\Domain\ClosingTextTemplate\ClosingTextTemplateFolder[] $folders */
/** @var \App\Domain\ClosingTextTemplate\ClosingTextTemplate[] $templates */
/** @var \App\Domain\ClosingTextTemplate\ClosingTextTemplateFolder|null $editingFolder */
/** @var \App\Domain\ClosingTextTemplate\ClosingTextTemplate|null $editingTemplate */
/** @var bool $isFolderForm */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

/**
 * Rich-Text-Editorfeld für den Vorlageninhalt — identisches Muster wie
 * renderDocumentClosingTextField() in invoices/form.php (bewusst erneut
 * dupliziert, siehe dortiger Kommentar zum selben Muster).
 */
if (!function_exists('renderClosingTextTemplateRichTextField')) {
    function renderClosingTextTemplateRichTextField(string $name, string $value, ?string $error): void
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

$byParent = [];
foreach ($folders as $folder) {
    $byParent[$folder->parentId ?? ''][] = $folder;
}
$templatesByFolder = [];
foreach ($templates as $template) {
    $templatesByFolder[$template->folderId ?? ''][] = $template;
}

$renderNode = function (?string $parentId, int $depth) use (&$renderNode, &$byParent, &$templatesByFolder, $editingFolder, $editingTemplate): void {
    foreach ($templatesByFolder[$parentId ?? ''] ?? [] as $template) {
        $isEditing = $editingTemplate !== null && $editingTemplate->id === $template->id;
        ?>
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-md px-3 py-2 <?= $isEditing ? 'bg-brand-50 ring-1 ring-brand-200 dark:bg-brand-950/40 dark:ring-brand-800' : 'hover:bg-slate-50 dark:hover:bg-slate-800' ?>" style="margin-left: <?= $depth * 20 ?>px">
            <span class="text-sm text-slate-800 dark:text-slate-200">📄 <?= e($template->name) ?></span>
            <span class="flex items-center gap-3">
                <a href="/einstellungen/textvorlagen/vorlagen/<?= e($template->id) ?>/bearbeiten" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('common.edit')) ?></a>
                <form method="post" action="/einstellungen/textvorlagen/vorlagen/<?= e($template->id) ?>/loeschen" data-confirm="Diese Textvorlage wirklich löschen?" data-confirm-style="danger">
                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                </form>
            </span>
        </div>
        <?php
    }
    foreach ($byParent[$parentId ?? ''] ?? [] as $folder) {
        $isEditing = $editingFolder !== null && $editingFolder->id === $folder->id;
        ?>
        <div class="flex flex-wrap items-center justify-between gap-2 rounded-md px-3 py-2 <?= $isEditing ? 'bg-brand-50 ring-1 ring-brand-200 dark:bg-brand-950/40 dark:ring-brand-800' : 'hover:bg-slate-50 dark:hover:bg-slate-800' ?>" style="margin-left: <?= $depth * 20 ?>px">
            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">📁 <?= e($folder->name) ?></span>
            <span class="flex items-center gap-3">
                <a href="/einstellungen/textvorlagen/ordner/<?= e($folder->id) ?>/bearbeiten" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400"><?= e(t('common.edit')) ?></a>
                <form method="post" action="/einstellungen/textvorlagen/ordner/<?= e($folder->id) ?>/loeschen" data-confirm="Diesen Ordner wirklich löschen?" data-confirm-style="danger">
                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400"><?= e(t('common.delete')) ?></button>
                </form>
            </span>
        </div>
        <?php
        $renderNode($folder->id, $depth + 1);
    }
};

// Flache Liste aller wählbaren Ordner (für beide Selects unten) statt
// direkter Ausgabe wie bei categories/index.php — hier zweimal benötigt
// (Ordner-Formular + Vorlagen-Formular), daher einmal eingesammelt.
$folderOptionsList = [];
$collectFolderOptions = function (?string $parentId, int $depth, ?string $excludeId) use (&$collectFolderOptions, &$byParent, &$folderOptionsList) {
    foreach ($byParent[$parentId ?? ''] ?? [] as $folder) {
        if ($excludeId !== null && $folder->id === $excludeId) {
            continue;
        }
        $folderOptionsList[] = ['id' => $folder->id, 'name' => $folder->name, 'depth' => $depth];
        $collectFolderOptions($folder->id, $depth + 1, $excludeId);
    }
};
$collectFolderOptions(null, 0, $editingFolder?->id);
?>

<div class="mb-6">
    <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100">Textvorlagen</h1>
    <p class="text-sm text-slate-500 dark:text-slate-400">Vorlagen für die Schlusstexte von Rechnungen, Angeboten und Auftragsbestätigungen, in Ordnern organisiert. Über den Button "Vorlage einfügen" neben dem Schlusstext-Feld im jeweiligen Formular nutzbar.</p>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <?php if ($folders === [] && $templates === []): ?>
            <p class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">Noch keine Textvorlagen angelegt.</p>
        <?php else: ?>
            <?php $renderNode(null, 0); ?>
        <?php endif; ?>
    </div>

    <div class="space-y-6">
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">
                <?= $editingFolder !== null ? 'Ordner bearbeiten' : 'Neuer Ordner' ?>
            </h2>
            <form
                method="post"
                action="<?= $editingFolder !== null ? '/einstellungen/textvorlagen/ordner/' . e($editingFolder->id) : '/einstellungen/textvorlagen/ordner' ?>"
                class="space-y-4"
            >
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <div>
                    <label for="folder_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name *</label>
                    <input
                        type="text" id="folder_name" name="name" required
                        value="<?= $isFolderForm ? e($old['name'] ?? $editingFolder?->name ?? '') : '' ?>"
                        class="mt-1 w-full rounded-md border <?= $isFolderForm && isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    >
                    <?php if ($isFolderForm && isset($errors['name'])): ?>
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="folder_parent_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Übergeordneter Ordner</label>
                    <select id="folder_parent_id" name="parent_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                        <option value="">Keiner (oberste Ebene)</option>
                        <?php
                        $selectedFolderParent = $isFolderForm ? ($old['parent_id'] ?? $editingFolder?->parentId) : null;
                        foreach ($folderOptionsList as $option):
                        ?>
                            <option value="<?= e($option['id']) ?>" <?= $selectedFolderParent === $option['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $option['depth']) ?><?= e($option['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isFolderForm && isset($errors['parentId'])): ?>
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['parentId']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
                    <?php if ($editingFolder !== null): ?>
                        <a href="/einstellungen/textvorlagen" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">
                <?= $editingTemplate !== null ? 'Textvorlage bearbeiten' : 'Neue Textvorlage' ?>
            </h2>
            <form
                method="post"
                action="<?= $editingTemplate !== null ? '/einstellungen/textvorlagen/vorlagen/' . e($editingTemplate->id) : '/einstellungen/textvorlagen/vorlagen' ?>"
                class="space-y-4"
                id="closing-text-template-form"
            >
                <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                <div>
                    <label for="template_name" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Name *</label>
                    <input
                        type="text" id="template_name" name="name" required
                        value="<?= !$isFolderForm ? e($old['name'] ?? $editingTemplate?->name ?? '') : '' ?>"
                        class="mt-1 w-full rounded-md border <?= !$isFolderForm && isset($errors['name']) ? 'border-red-400 dark:border-red-600' : 'border-slate-300 dark:border-slate-700' ?> px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500"
                    >
                    <?php if (!$isFolderForm && isset($errors['name'])): ?>
                        <p class="mt-1 text-xs text-red-600 dark:text-red-400"><?= e($errors['name']) ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <label for="template_folder_id" class="block text-xs font-medium text-slate-600 dark:text-slate-400">Ordner</label>
                    <select id="template_folder_id" name="folder_id" class="mt-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700">
                        <option value="">Keiner (oberste Ebene)</option>
                        <?php
                        $selectedTemplateFolder = !$isFolderForm ? ($old['folder_id'] ?? $editingTemplate?->folderId) : null;
                        $allFolderOptions = [];
                        $collectAllFolderOptions = function (?string $parentId, int $depth) use (&$collectAllFolderOptions, &$byParent, &$allFolderOptions) {
                            foreach ($byParent[$parentId ?? ''] ?? [] as $folder) {
                                $allFolderOptions[] = ['id' => $folder->id, 'name' => $folder->name, 'depth' => $depth];
                                $collectAllFolderOptions($folder->id, $depth + 1);
                            }
                        };
                        $collectAllFolderOptions(null, 0);
                        foreach ($allFolderOptions as $option):
                        ?>
                            <option value="<?= e($option['id']) ?>" <?= $selectedTemplateFolder === $option['id'] ? 'selected' : '' ?>><?= str_repeat('— ', $option['depth']) ?><?= e($option['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 dark:text-slate-400">Inhalt</label>
                    <div class="mt-1">
                        <?php renderClosingTextTemplateRichTextField('content', !$isFolderForm ? ($old['content'] ?? $editingTemplate?->content ?? '') : '', !$isFolderForm ? ($errors['content'] ?? null) : null); ?>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
                    <?php if ($editingTemplate !== null): ?>
                        <a href="/einstellungen/textvorlagen" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
  // WYSIWYG-Editor für den Vorlageninhalt — identisches Muster wie in
  // invoices/form.php (siehe dortiger Kommentar).
  (function () {
    try {
      document.execCommand('defaultParagraphSeparator', false, 'p');
    } catch (e) { /* ältere Browser: ignorieren */ }

    document.querySelectorAll('[data-rt-field]').forEach(function (field) {
      var toolbar = field.querySelector('[data-rt-toolbar]');
      var editable = field.querySelector('[data-rt-editable]');
      var textarea = field.querySelector('[data-rt-source]');

      if (!toolbar || !editable || !textarea) { return; }

      editable.innerHTML = textarea.value || '<p></p>';
      toolbar.classList.remove('hidden');
      toolbar.classList.add('flex');
      editable.classList.remove('hidden');
      textarea.classList.add('hidden');

      function sync() { textarea.value = editable.innerHTML; }

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
          if (headingSelect.value) { document.execCommand('formatBlock', false, headingSelect.value); }
          headingSelect.value = '';
          sync();
        });
      }

      var ownForm = field.closest('form');
      if (ownForm) {
        ownForm.addEventListener('submit', function () {
          if (!editable.classList.contains('hidden')) { textarea.value = editable.innerHTML; }
        });
      }
    });
  })();
</script>
