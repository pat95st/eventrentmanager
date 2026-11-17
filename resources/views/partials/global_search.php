<?php
/**
 * Global search dialog (Ctrl+K / Cmd+K), opened by the search button in the
 * header (see layout.php) or by the shortcut. Behaviour lives in
 * public/assets/js/global-search.js, styling in
 * public/assets/css/global-search.css; results come from GET /suche
 * (GlobalSearchController).
 *
 * @var \App\Domain\User\User|null $currentUser
 */

use App\Application\Search\Query\GlobalSearchHandler;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

if (($currentUser ?? null) === null) {
    return;
}

// One icon per result group (24x24 outline paths), keyed by
// App\Application\Search\SearchResultType.
$searchIcons = [
    'rental' => ['M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5'],
    'article' => ['m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25M3 7.5l9 5.25M3 7.5v9l9 5.25m0-9v9'],
    'customer' => ['M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z'],
    'supplier' => ['M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12'],
    'technician' => ['M21.75 6.75a4.5 4.5 0 0 1-4.884 4.484c-1.076-.091-2.264.071-2.95.904l-7.152 8.684a2.548 2.548 0 1 1-3.586-3.586l8.684-7.152c.833-.686.995-1.874.904-2.95a4.5 4.5 0 0 1 6.336-4.486l-3.276 3.276a3.004 3.004 0 0 0 2.25 2.25l3.276-3.276c.256.565.398 1.192.398 1.852Z', 'M4.867 19.125h.008v.008h-.008v-.008Z'],
    'venue' => ['M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z', 'M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z'],
    'purchase_order' => ['M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z'],
    'receipt' => ['M6 3.75h12v16.5l-2-1.25-2 1.25-2-1.25-2 1.25-2-1.25-2 1.25V3.75Z', 'M9 8.25h6M9 12h6'],
    'document' => ['M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z'],
];
$renderSearchIcon = static function (string $type) use ($searchIcons): string {
    $paths = '';
    foreach ($searchIcons[$type] ?? [] as $path) {
        $paths .= '<path d="' . e($path) . '"/>';
    }

    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
};

$searchTypes = GlobalSearchHandler::searchableTypes($currentUser);
if ($searchTypes === []) {
    return;
}

$searchConfig = [
    'url' => '/suche',
    'minChars' => GlobalSearchHandler::MIN_TERM_LENGTH,
    'types' => array_map(static fn ($type): array => ['key' => $type->value, 'label' => t('search.type.' . $type->value)], $searchTypes),
    'labels' => [
        'empty' => t('search.empty'),
        'error' => t('search.error'),
        'loading' => t('search.loading'),
        'resultsCount' => t('search.results_count'),
    ],
];
?>
<dialog id="global-search" class="gs-dialog" data-modal-fade="off" aria-label="<?= e(t('search.title')) ?>">
  <div class="gs-panel">
    <div class="gs-input-row">
      <svg class="gs-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
      <input
        type="text"
        id="global-search-input"
        class="gs-input"
        role="combobox"
        aria-label="<?= e(t('search.title')) ?>"
        aria-controls="global-search-results"
        aria-expanded="false"
        aria-autocomplete="list"
        autocomplete="off"
        autocapitalize="off"
        spellcheck="false"
        enterkeyhint="go"
        maxlength="100"
        placeholder="<?= e(t('search.placeholder')) ?>"
      >
      <span class="gs-spinner" data-gs-spinner hidden aria-hidden="true"></span>
      <button type="button" class="gs-close" data-gs-close aria-label="<?= e(t('common.close')) ?>"><kbd class="gs-kbd">Esc</kbd><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
    </div>

    <div class="gs-body">
      <!-- Start state: what can be searched (depends on the user's permissions). -->
      <div class="gs-intro" data-gs-intro>
        <p><?= e(t('search.hint', ['min' => (string) GlobalSearchHandler::MIN_TERM_LENGTH])) ?></p>
        <p class="gs-intro-label"><?= e(t('search.searches')) ?></p>
        <ul class="gs-chips">
          <?php foreach ($searchTypes as $type): ?>
            <li class="gs-chip"><?= $renderSearchIcon($type->value) ?><?= e(t('search.type.' . $type->value)) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <p class="gs-message" data-gs-message hidden></p>
      <div id="global-search-results" class="gs-results" role="listbox" aria-label="<?= e(t('search.title')) ?>" data-gs-results hidden></div>
    </div>

    <div class="gs-footer" aria-hidden="true">
      <span><kbd class="gs-kbd">↑</kbd><kbd class="gs-kbd">↓</kbd> <?= e(t('search.key.navigate')) ?></span>
      <span><kbd class="gs-kbd">↵</kbd> <?= e(t('search.key.open')) ?></span>
      <span><kbd class="gs-kbd">Esc</kbd> <?= e(t('search.key.close')) ?></span>
    </div>
    <span class="sr-only" role="status" aria-live="polite" data-gs-live></span>
  </div>

  <?php foreach ($searchTypes as $type): ?>
    <template data-gs-icon="<?= e($type->value) ?>"><?= $renderSearchIcon($type->value) ?></template>
  <?php endforeach; ?>
</dialog>
<script>window.GLOBAL_SEARCH = <?= json_encode($searchConfig, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="/assets/js/global-search.js?v=<?= e((string) (@filemtime(dirname(__DIR__, 3) . '/public/assets/js/global-search.js') ?: 1)) ?>" defer></script>
