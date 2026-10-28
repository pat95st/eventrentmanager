/**
 * Mehrfachauswahl-Modal für Artikel ("mehrere Artikel auf einmal
 * hinzufügen") — ergänzt die bestehende Live-Suche (live-search.js) in den
 * Positionszeilen von Angebots-/Rechnungs-/Auftragsbestätigungsformularen
 * um einen zweiten, parallelen Weg: statt Zeile für Zeile einzeln über die
 * Live-Suche einen Artikel auszuwählen, können hier mehrere Artikel aus dem
 * kompletten, paginiert durchsuchbaren Katalog (ArticleController::picker(),
 * GET /artikel/mehrfachauswahl) angehakt und mit je einer eigenen Menge
 * gesammelt übernommen werden.
 *
 * Markup: siehe App\Infrastructure\Http\renderArticlePickerModal() — EIN
 * Modal pro Formular (wie beim Textvorlagen-Picker, siehe
 * renderClosingTextTemplatePickerModal() in resources/views/invoices/
 * form.php), unabhängig davon, wie viele Artikel-Zeilenbereiche ein
 * Formular hat (Angebote haben z. B. einen separaten Verkaufspositionen-
 * Bereich, der hier bewusst NICHT bedient wird — siehe Begründung im
 * jeweiligen <script>-Block der Formulare).
 *
 * Bewusst als EINE globale, gemeinsam genutzte Datei (wie live-search.js
 * direkt daneben), da dieselbe Modal-Instanz und -Logik von bis zu drei
 * verschiedenen Formularen unverändert wiederverwendet wird. Die eigentliche
 * Übernahme der ausgewählten Artikel als Positionszeilen ist bewusst NICHT
 * Teil dieser Datei — jedes Formular kennt sein eigenes addRow(data) (siehe
 * invoices/form.php, offers/form.php, order-confirmations/form.php) und
 * übergibt es als Callback an window.ArticlePicker.open().
 *
 * Texte bewusst fest im Skript (kein i18n-Mechanismus) — identisch zum
 * Vorbild live-search.js ("Keine Treffer." dort), das aus demselben Grund
 * ebenfalls keine Übersetzung seiner JS-seitig erzeugten Texte anbietet.
 */
window.ArticlePicker = (function () {
    'use strict';

    var DEBOUNCE_MS = 250;
    var TEXT = {
        loading: 'Lädt…',
        empty: 'Keine Artikel gefunden.',
        quantityLabel: 'Menge',
        prevPage: '← Zurück',
        nextPage: 'Weiter →',
        pageInfo: 'Seite {page} von {totalPages}',
        selectedCount: '{count} ausgewählt',
    };

    var modal = null;
    var searchInput = null;
    var resultsEl = null;
    var paginationEl = null;
    var countEl = null;
    var insertButton = null;
    var cancelButton = null;
    var closeButton = null;

    var onInsertCallback = null;
    var selected = {}; // id -> { label: string, quantity: string }
    var currentPage = 1;
    var currentTotalPages = 1;
    var activeRequest = null;
    var debounceTimer = null;

    function ensureInit() {
        if (modal) {
            return true;
        }

        modal = document.querySelector('[data-article-picker-modal]');
        if (!modal) {
            // Formular hat keinen renderArticlePickerModal()-Aufruf (sollte
            // bei allen drei Dokumentformularen der Fall sein) — kein Fehler,
            // open() wird dann einfach nicht aufgerufen.
            return false;
        }

        searchInput = modal.querySelector('[data-article-picker-search]');
        resultsEl = modal.querySelector('[data-article-picker-results]');
        paginationEl = modal.querySelector('[data-article-picker-pagination]');
        countEl = modal.querySelector('[data-article-picker-count]');
        insertButton = modal.querySelector('[data-article-picker-insert]');
        cancelButton = modal.querySelector('[data-article-picker-cancel]');
        closeButton = modal.querySelector('[data-article-picker-close]');

        searchInput.addEventListener('input', function () {
            currentPage = 1;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(search, DEBOUNCE_MS);
        });
        insertButton.addEventListener('click', handleInsert);
        cancelButton.addEventListener('click', close);
        closeButton.addEventListener('click', close);
        modal.addEventListener('mousedown', function (event) {
            if (event.target === modal) { close(); }
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) { close(); }
        });

        return true;
    }

    function open(onInsert) {
        if (!ensureInit()) {
            return;
        }

        onInsertCallback = onInsert;
        selected = {};
        currentPage = 1;
        searchInput.value = '';
        updateCount();

        modal.classList.remove('hidden');
        modal.classList.add('flex');
        search();
        window.setTimeout(function () { searchInput.focus(); }, 0);
    }

    function close() {
        if (activeRequest) { activeRequest.abort(); }
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    function search() {
        if (activeRequest) { activeRequest.abort(); }

        var controller = new AbortController();
        activeRequest = controller;

        resultsEl.innerHTML = '<p class="py-6 text-center text-slate-400 dark:text-slate-500">' + TEXT.loading + '</p>';
        paginationEl.innerHTML = '';

        var url = '/artikel/mehrfachauswahl?q=' + encodeURIComponent(searchInput.value) + '&seite=' + currentPage;

        fetch(url, { headers: { 'Accept': 'application/json' }, signal: controller.signal })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                activeRequest = null;
                renderResults(data);
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') { activeRequest = null; }
            });
    }

    function renderResults(data) {
        currentPage = data.page || 1;
        currentTotalPages = data.totalPages || 1;

        resultsEl.innerHTML = '';

        if (data.items.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'py-6 text-center text-slate-400 dark:text-slate-500';
            empty.textContent = TEXT.empty;
            resultsEl.appendChild(empty);
            renderPagination();
            return;
        }

        var list = document.createElement('div');
        list.className = 'divide-y divide-slate-100 dark:divide-slate-800';

        data.items.forEach(function (item) {
            var existing = selected[item.id];

            var row = document.createElement('label');
            row.className = 'flex items-center gap-3 py-2';

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'h-4 w-4 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600';
            checkbox.checked = !!existing;

            var textWrap = document.createElement('span');
            textWrap.className = 'min-w-0 flex-1 truncate';
            textWrap.textContent = item.label;
            if (item.sublabel) {
                var subSpan = document.createElement('span');
                subSpan.className = 'ml-2 text-xs text-slate-400 dark:text-slate-500';
                subSpan.textContent = item.sublabel;
                textWrap.appendChild(subSpan);
            }

            var quantityInput = document.createElement('input');
            quantityInput.type = 'number';
            quantityInput.min = '1';
            quantityInput.value = existing ? existing.quantity : '1';
            quantityInput.disabled = !existing;
            quantityInput.title = TEXT.quantityLabel;
            quantityInput.className = 'w-16 shrink-0 rounded-md border border-slate-300 px-2 py-1 text-xs dark:border-slate-700';

            checkbox.addEventListener('change', function () {
                if (checkbox.checked) {
                    selected[item.id] = { label: item.label, quantity: quantityInput.value || '1' };
                } else {
                    delete selected[item.id];
                }
                quantityInput.disabled = !checkbox.checked;
                updateCount();
            });

            quantityInput.addEventListener('input', function () {
                if (selected[item.id]) { selected[item.id].quantity = quantityInput.value || '1'; }
            });
            // Klick auf das Mengenfeld soll nicht die Checkbox umschalten
            // (das umschließende <label> würde das sonst auslösen).
            quantityInput.addEventListener('click', function (event) { event.stopPropagation(); });

            row.appendChild(checkbox);
            row.appendChild(textWrap);
            row.appendChild(quantityInput);
            list.appendChild(row);
        });

        resultsEl.appendChild(list);
        renderPagination();
    }

    function renderPagination() {
        paginationEl.innerHTML = '';
        if (currentTotalPages <= 1) {
            return;
        }

        var prev = document.createElement('button');
        prev.type = 'button';
        prev.textContent = TEXT.prevPage;
        prev.disabled = currentPage <= 1;
        prev.className = 'rounded-md border border-slate-300 px-2 py-1 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700';
        prev.addEventListener('click', function () { currentPage -= 1; search(); });

        var info = document.createElement('span');
        info.textContent = TEXT.pageInfo
            .replace('{page}', String(currentPage))
            .replace('{totalPages}', String(currentTotalPages));

        var next = document.createElement('button');
        next.type = 'button';
        next.textContent = TEXT.nextPage;
        next.disabled = currentPage >= currentTotalPages;
        next.className = 'rounded-md border border-slate-300 px-2 py-1 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700';
        next.addEventListener('click', function () { currentPage += 1; search(); });

        paginationEl.appendChild(prev);
        paginationEl.appendChild(info);
        paginationEl.appendChild(next);
    }

    function updateCount() {
        var count = Object.keys(selected).length;
        countEl.textContent = count > 0 ? TEXT.selectedCount.replace('{count}', String(count)) : '';
        insertButton.disabled = count === 0;
    }

    function handleInsert() {
        var items = Object.keys(selected).map(function (id) {
            return { article_id: id, quantity: selected[id].quantity || '1' };
        });
        close();
        if (onInsertCallback) { onInsertCallback(items); }
    }

    return { open: open };
})();
