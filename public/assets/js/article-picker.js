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
        noFilterOptions: 'Keine Einträge vorhanden.',
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

    // Mehrfachfilter Hersteller/Kategorie: gewählte IDs je Filter (bleiben
    // beim erneuten Öffnen des Modals innerhalb derselben Seite erhalten),
    // Optionen werden einmalig mit der ersten Suche geladen.
    var filterState = { manufacturers: {}, categories: {} };
    var filterOptionsLoaded = false;
    var filterEls = {};
    var filterResetButton = null;

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
        filterResetButton = modal.querySelector('[data-article-picker-filter-reset]');

        modal.querySelectorAll('[data-article-picker-filter]').forEach(function (wrapper) {
            var key = wrapper.getAttribute('data-article-picker-filter');
            var els = {
                wrapper: wrapper,
                toggle: wrapper.querySelector('[data-article-picker-filter-toggle]'),
                count: wrapper.querySelector('[data-article-picker-filter-count]'),
                panel: wrapper.querySelector('[data-article-picker-filter-panel]'),
                search: wrapper.querySelector('[data-article-picker-filter-search]'),
                options: wrapper.querySelector('[data-article-picker-filter-options]'),
            };
            filterEls[key] = els;

            els.toggle.addEventListener('click', function () {
                var willOpen = els.panel.classList.contains('hidden');
                closeFilterPanels();
                if (willOpen) {
                    els.panel.classList.remove('hidden');
                    els.search.value = '';
                    applyOptionSearch(els);
                    els.search.focus();
                }
            });
            els.search.addEventListener('input', function () { applyOptionSearch(els); });
        });
        if (filterResetButton) {
            filterResetButton.addEventListener('click', function () {
                filterState = { manufacturers: {}, categories: {} };
                Object.keys(filterEls).forEach(function (key) {
                    filterEls[key].options.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = false; });
                });
                updateFilterBadges();
                currentPage = 1;
                search();
            });
        }
        // Klick außerhalb eines Filter-Panels schließt es.
        modal.addEventListener('mousedown', function (event) {
            if (!event.target.closest('[data-article-picker-filter]')) { closeFilterPanels(); }
        });

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
            if (event.key !== 'Escape' || modal.classList.contains('hidden')) { return; }
            // Erst ein offenes Filter-Panel schließen, erst beim zweiten Escape das Modal.
            if (anyFilterPanelOpen()) { closeFilterPanels(); return; }
            close();
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
        closeFilterPanels();
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

        var url = '/artikel/mehrfachauswahl?q=' + encodeURIComponent(searchInput.value) + '&seite=' + currentPage
            + '&hersteller=' + encodeURIComponent(Object.keys(filterState.manufacturers).join(','))
            + '&kategorien=' + encodeURIComponent(Object.keys(filterState.categories).join(','))
            + (filterOptionsLoaded ? '' : '&optionen=1');

        fetch(url, { headers: { 'Accept': 'application/json' }, signal: controller.signal })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                activeRequest = null;
                if (!filterOptionsLoaded && data.manufacturers && data.categories) {
                    renderFilterOptions('manufacturers', data.manufacturers);
                    renderFilterOptions('categories', data.categories);
                    filterOptionsLoaded = true;
                }
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

    function renderFilterOptions(key, options) {
        var els = filterEls[key];
        if (!els) { return; }
        els.options.innerHTML = '';
        if (options.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'px-1 py-2 text-slate-400 dark:text-slate-500';
            empty.textContent = TEXT.noFilterOptions;
            els.options.appendChild(empty);
            return;
        }
        options.forEach(function (option) {
            var row = document.createElement('label');
            row.className = 'flex cursor-pointer items-center gap-2 rounded px-1 py-1 hover:bg-slate-50 dark:hover:bg-slate-800';
            row.setAttribute('data-option-label', option.label.toLowerCase());

            var checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'h-3.5 w-3.5 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500 dark:border-slate-600';
            checkbox.checked = !!filterState[key][option.id];
            checkbox.addEventListener('change', function () {
                if (checkbox.checked) {
                    filterState[key][option.id] = true;
                } else {
                    delete filterState[key][option.id];
                }
                updateFilterBadges();
                currentPage = 1;
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(search, DEBOUNCE_MS);
            });

            var text = document.createElement('span');
            text.className = 'min-w-0 flex-1 truncate';
            text.textContent = option.label;
            text.title = option.label;

            row.appendChild(checkbox);
            row.appendChild(text);
            els.options.appendChild(row);
        });
    }

    function applyOptionSearch(els) {
        var term = els.search.value.trim().toLowerCase();
        els.options.querySelectorAll('[data-option-label]').forEach(function (row) {
            row.classList.toggle('hidden', term !== '' && row.getAttribute('data-option-label').indexOf(term) === -1);
        });
    }

    function updateFilterBadges() {
        var total = 0;
        Object.keys(filterEls).forEach(function (key) {
            var count = Object.keys(filterState[key]).length;
            total += count;
            filterEls[key].count.textContent = String(count);
            filterEls[key].count.classList.toggle('hidden', count === 0);
        });
        if (filterResetButton) { filterResetButton.classList.toggle('hidden', total === 0); }
    }

    function closeFilterPanels() {
        Object.keys(filterEls).forEach(function (key) { filterEls[key].panel.classList.add('hidden'); });
    }

    function anyFilterPanelOpen() {
        return Object.keys(filterEls).some(function (key) { return !filterEls[key].panel.classList.contains('hidden'); });
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
