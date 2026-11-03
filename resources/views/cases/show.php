<?php
/** @var \App\Application\CaseItem\Query\CaseItemDetail $detail */
/** @var string|null $editInventoryId */
/** @var \App\Domain\Article\Article[] $articles */
/** @var array<string, string> $articleDisplayNames Artikel-ID => "Hersteller Artikelbezeichnung" */
/** @var array<string, string> $errors */
/** @var array<string, mixed> $old */

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

$case = $detail->case;

/**
 * WYSIWYG-Feld für den Hinweistext eines Exemplars (Migration 114) — gleiches
 * Muster wie renderOfferPdfRichTextField() in settings/offer-pdf-template.php
 * (Textarea mit bereinigtem HTML, per JS durch contenteditable ersetzt;
 * Skript am Ende dieser Datei). Bewusst dupliziert, siehe dortiger Kommentar.
 */
if (!function_exists('renderCaseNoticeField')) {
    function renderCaseNoticeField(string $name, string $value): void
    {
        $button = 'rounded px-2 py-1 text-xs text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700';
        ?>
    <div data-rt-field>
        <div data-rt-toolbar class="hidden flex-wrap gap-1 rounded-t-md border border-b-0 border-slate-300 bg-slate-50 p-1.5 dark:border-slate-700 dark:bg-slate-800">
            <button type="button" data-cmd="bold" title="<?= e(t('rich_text.toolbar.bold')) ?>" class="<?= $button ?> font-bold">F</button>
            <button type="button" data-cmd="italic" title="<?= e(t('rich_text.toolbar.italic')) ?>" class="<?= $button ?> italic">K</button>
            <button type="button" data-cmd="underline" title="<?= e(t('rich_text.toolbar.underline')) ?>" class="<?= $button ?> underline">U</button>
            <select data-heading-select title="<?= e(t('rich_text.toolbar.heading')) ?>" class="rounded border border-slate-300 bg-white px-1.5 py-1 text-xs text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <option value=""><?= e(t('rich_text.toolbar.heading_placeholder')) ?></option>
                <option value="H2"><?= e(t('rich_text.toolbar.heading2')) ?></option>
                <option value="H3"><?= e(t('rich_text.toolbar.heading3')) ?></option>
                <option value="P"><?= e(t('rich_text.toolbar.paragraph')) ?></option>
            </select>
            <button type="button" data-cmd="insertUnorderedList" class="<?= $button ?>"><?= e(t('rich_text.toolbar.bullet_list')) ?></button>
            <button type="button" data-cmd="insertOrderedList" class="<?= $button ?>"><?= e(t('rich_text.toolbar.numbered_list')) ?></button>
        </div>
        <div data-rt-editable contenteditable="true" class="hidden min-h-[8rem] rounded-b-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-1 focus:ring-brand-500 dark:border-slate-700 dark:bg-slate-950"></div>
        <textarea data-rt-source name="<?= e($name) ?>" rows="5" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-700"><?= e($value) ?></textarea>
    </div>
        <?php
    }
}
?>

<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <a href="/cases" class="text-sm text-slate-500 hover:underline dark:text-slate-400">&larr; Alle Cases</a>
        <h1 class="mt-1 text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e($case->name) ?></h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">
            <?= e($detail->manufacturer->name) ?>
        </p>
    </div>
    <div class="flex items-center gap-3">
        <a href="/cases/<?= e($case->id) ?>/bearbeiten" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
            Bearbeiten
        </a>
        <form method="post" action="/cases/<?= e($case->id) ?>/loeschen" data-confirm="Diesen Case inklusive aller Exemplare und deren Inhalt wirklich löschen?" data-confirm-style="danger">
          <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
            <button type="submit" class="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50 dark:border-red-700 dark:text-red-400 dark:hover:bg-red-950/40">
                Löschen
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 space-y-6">
        <!-- Stammdaten -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Details</h2>
            <?php if ($case->description !== null): ?>
                <dd class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-300"><?= e($case->description) ?></dd>
            <?php else: ?>
                <p class="text-sm text-slate-400 dark:text-slate-500">Keine Beschreibung hinterlegt.</p>
            <?php endif; ?>
        </div>

        <!-- Case-Exemplare -->
        <div class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-semibold text-slate-700 dark:text-slate-300">Case-Exemplare</h2>

            <?php if ($detail->inventoryNumbers === []): ?>
                <p class="mb-4 text-sm text-slate-500 dark:text-slate-400">Noch kein Exemplar angelegt.</p>
            <?php else: ?>
                <div class="mb-4 space-y-4">
                    <?php foreach ($detail->inventoryNumbers as $entry): ?>
                        <?php $inv = $entry->inventoryNumber; ?>
                        <?php
                            $articleCount = array_sum(array_map(static fn ($c) => $c->item->quantity, $entry->content));
                            $photoCount = count($entry->images);
                        ?>
                        <div id="exemplar-<?= e($inv->id) ?>" class="overflow-hidden rounded-md border border-slate-200 dark:border-slate-700">
                            <!-- Kopf des Exemplars: Inventurnummer und Stückliste (eine pro Case-Exemplar) -->
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-slate-100 px-3 py-2.5 dark:border-slate-700 dark:bg-slate-800">
                                <div>
                                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Case-Exemplar</p>
                                    <p class="font-mono text-base font-semibold text-slate-900 dark:text-slate-100"><?= e($inv->number) ?></p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        <?= $articleCount ?> Teile · <?= $photoCount ?> Foto<?= $photoCount === 1 ? '' : 's' ?> · Angeschafft: <?= $inv->acquisitionDate !== null ? e($inv->acquisitionDate->format('d.m.Y')) : '–' ?>
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <a href="/cases/<?= e($case->id) ?>/stueckliste.pdf?exemplar=<?= e($inv->id) ?>" target="_blank" class="inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width:1rem;height:1rem"><path d="M4 2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.414A2 2 0 0 0 17.414 6L14 2.586A2 2 0 0 0 12.586 2H4Zm6 5a1 1 0 0 1 1 1v3.586l1.293-1.293a1 1 0 1 1 1.414 1.414l-3 3a1 1 0 0 1-1.414 0l-3-3a1 1 0 1 1 1.414-1.414L9 11.586V8a1 1 0 0 1 1-1Z"/></svg>
                                        Stückliste (PDF)
                                    </a>
                                    <?php if ($editInventoryId !== $inv->id): ?>
                                        <a href="/cases/<?= e($case->id) ?>?edit_inventory=<?= e($inv->id) ?>" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Bearbeiten</a>
                                    <?php endif; ?>
                                    <form method="post" action="/case-inventurnummern/<?= e($inv->id) ?>/loeschen" data-confirm="Dieses Case-Exemplar inklusive Inhalt löschen?" data-confirm-style="danger">
                                        <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                        <input type="hidden" name="case_id" value="<?= e($case->id) ?>">
                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Löschen</button>
                                    </form>
                                </div>
                            </div>

                            <?php if ($editInventoryId === $inv->id): ?>
                                <form method="post" action="/case-inventurnummern/<?= e($inv->id) ?>" class="flex flex-wrap items-end gap-3 border-b border-slate-100 p-3 dark:border-slate-800">
                                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <input type="hidden" name="case_id" value="<?= e($case->id) ?>">
                                    <div>
                                        <label class="block text-xs text-slate-500 dark:text-slate-400">Anschaffungszeitpunkt</label>
                                        <input type="date" name="acquisition_date" value="<?= e($inv->acquisitionDate?->format('Y-m-d')) ?>"
                                               class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                                    </div>
                                    <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700">Speichern</button>
                                    <a href="/cases/<?= e($case->id) ?>" class="text-sm text-slate-500 hover:underline dark:text-slate-400">Abbrechen</a>
                                </form>
                            <?php endif; ?>

                            <!-- Inhalt dieses Exemplars -->
                            <div class="border-t border-slate-100 bg-slate-50/60 p-3 dark:border-slate-800 dark:bg-slate-800/40">
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Inhalt</p>
                                <?php if ($entry->content === []): ?>
                                    <p class="mb-2 text-xs text-slate-400 dark:text-slate-500">Noch keine Artikel zugeordnet.</p>
                                <?php else: ?>
                                    <div class="mb-2 divide-y divide-slate-200 dark:divide-slate-700">
                                        <?php foreach ($entry->content as $contentEntry): ?>
                                            <div class="flex flex-wrap items-center justify-between gap-2 py-1.5 text-sm">
                                                <span class="text-slate-700 dark:text-slate-300"><?= e($contentEntry->displayName()) ?></span>
                                                <div class="flex items-center gap-3">
                                                    <form method="post" action="/case-inhalt/<?= e($contentEntry->item->id) ?>" class="flex items-center gap-1">
                                                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                                        <input type="hidden" name="case_id" value="<?= e($case->id) ?>">
                                                        <input type="number" name="quantity" min="1" value="<?= e((string) $contentEntry->item->quantity) ?>"
                                                               class="w-16 rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700">
                                                        <button type="submit" class="text-xs font-medium text-brand-700 hover:underline dark:text-brand-400">Ändern</button>
                                                    </form>
                                                    <form method="post" action="/case-inhalt/<?= e($contentEntry->item->id) ?>/loeschen" data-confirm="Artikel aus diesem Case entfernen?" data-confirm-style="danger">
                                                      <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                                        <input type="hidden" name="case_id" value="<?= e($case->id) ?>">
                                                        <button type="submit" class="text-xs font-medium text-red-600 hover:underline dark:text-red-400">Entfernen</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <form method="post" action="/case-inventurnummern/<?= e($inv->id) ?>/inhalt" class="flex flex-wrap items-end gap-2">
                                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <input type="hidden" name="case_id" value="<?= e($case->id) ?>">
                                    <div>
                                        <label class="block text-[11px] text-slate-500 dark:text-slate-400">Artikel</label>
                                        <select name="article_id" required class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700">
                                            <option value="">Bitte wählen…</option>
                                            <?php foreach ($articles as $article): ?>
                                                <option value="<?= e($article->id) ?>"><?= e($articleDisplayNames[$article->id] ?? $article->name) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[11px] text-slate-500 dark:text-slate-400">Menge</label>
                                        <input type="number" name="quantity" min="1" value="1" class="mt-1 w-16 rounded-md border border-slate-300 px-2 py-1.5 text-xs dark:border-slate-700">
                                    </div>
                                    <button type="submit" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                        Hinzufügen
                                    </button>
                                </form>
                            </div>

                            <!-- Fotos vom befüllten Case (Migration 114) -->
                            <div class="border-t border-slate-100 p-3 dark:border-slate-800">
                                <p class="mb-1 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Fotos vom befüllten Case</p>
                                <p class="mb-2 text-[11px] text-slate-400 dark:text-slate-500">Erscheinen auf der Stückliste, damit alle Teile wieder richtig einsortiert werden.</p>
                                <?php if ($entry->images !== []): ?>
                                    <div class="mb-3 flex flex-wrap gap-2">
                                        <?php foreach ($entry->images as $image): ?>
                                            <div class="group relative overflow-hidden rounded-md border border-slate-200 dark:border-slate-700" style="width:7rem;height:7rem">
                                                <a href="/cases/<?= e($case->id) ?>/bild?image=<?= e($image->id) ?>" target="_blank">
                                                    <img src="/cases/<?= e($case->id) ?>/bild?image=<?= e($image->id) ?>" alt="Foto des befüllten Cases" loading="lazy" class="object-cover" style="width:100%;height:100%">
                                                </a>
                                                <form method="post" action="/case-bilder/<?= e($image->id) ?>/loeschen" data-confirm="Dieses Foto entfernen?" data-confirm-style="danger" class="absolute right-1 top-1">
                                                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                                    <input type="hidden" name="case_id" value="<?= e($case->id) ?>">
                                                    <input type="hidden" name="inventory_number_id" value="<?= e($inv->id) ?>">
                                                    <button type="submit" title="Foto entfernen" class="rounded bg-white/90 px-1.5 text-xs font-bold text-red-600 shadow hover:bg-white">×</button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <form method="post" action="/case-inventurnummern/<?= e($inv->id) ?>/bilder" enctype="multipart/form-data" class="flex flex-wrap items-center gap-2">
                                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <input type="hidden" name="case_id" value="<?= e($case->id) ?>">
                                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple required class="text-xs text-slate-600 dark:text-slate-300">
                                    <button type="submit" class="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Hochladen</button>
                                </form>
                            </div>

                            <!-- Hinweistext für die Stückliste (Migration 114) -->
                            <div class="border-t border-slate-100 p-3 dark:border-slate-800">
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Hinweistext für die Stückliste</p>
                                <form method="post" action="/case-inventurnummern/<?= e($inv->id) ?>/hinweis" data-rt-form class="space-y-2">
                                    <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                                    <input type="hidden" name="case_id" value="<?= e($case->id) ?>">
                                    <?php renderCaseNoticeField('notice_html', $inv->noticeHtml ?? ''); ?>
                                    <button type="submit" class="rounded-md bg-brand-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-700">Hinweis speichern</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($detail->nextInventoryNumberPreview === null): ?>
                <div class="border-t border-slate-100 pt-4 dark:border-slate-800">
                    <p class="rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-800">
                        Die Nummerierung für Case-Inventurnummern ist noch nicht eingerichtet (Migration 120, siehe Einstellungen › Nummerierung).
                    </p>
                </div>
            <?php else: ?>
                <form method="post" action="/cases/<?= e($case->id) ?>/inventurnummern" class="flex flex-wrap items-end gap-3 border-t border-slate-100 pt-4 dark:border-slate-800">
                  <input type="hidden" name="csrf_token" value="<?= e(\App\Infrastructure\Http\Csrf::token()) ?>">
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400">Neue Inventurnummer</label>
                        <p class="mt-1 rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5 font-mono text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-400"><?= e($detail->nextInventoryNumberPreview) ?></p>
                        <p class="mt-1 text-[11px] text-slate-400 dark:text-slate-500">Wird automatisch vergeben.</p>
                    </div>
                    <div>
                        <label class="block text-xs text-slate-500 dark:text-slate-400">Anschaffungszeitpunkt</label>
                        <input type="date" name="acquisition_date" class="mt-1 rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700">
                    </div>
                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        Hinzufügen
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Hinweis</h2>
            <p class="text-slate-500 dark:text-slate-400">
                Jedes Case-Exemplar hat eine eigene Inventurnummer. Artikel werden pro Exemplar
                mit Menge zugeordnet, unabhängig von der konkreten Inventurnummer der Artikel selbst.
            </p>
        </div>
    </div>
</div>

<script>
  // WYSIWYG-Editor für die Hinweistexte, siehe renderCaseNoticeField() oben
  // (gleiches Skript wie in settings/offer-pdf-template.php).
  (function () {
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}

    document.querySelectorAll('[data-rt-field]').forEach(function (field) {
      var toolbar = field.querySelector('[data-rt-toolbar]');
      var editable = field.querySelector('[data-rt-editable]');
      var textarea = field.querySelector('[data-rt-source]');
      if (!toolbar || !editable || !textarea) { return; }

      editable.innerHTML = textarea.value || '<p><br></p>';
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

      var form = field.closest('form');
      if (form) { form.addEventListener('submit', sync); }
    });
  })();
</script>
