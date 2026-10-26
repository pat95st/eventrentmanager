/**
 * Live-Suche (Autocomplete) für Datensatz-Auswahlfelder (Kunden,
 * Veranstaltungsorte, Artikel, Hersteller, Lieferanten usw.), als Ersatz
 * für einfache <select>-Dropdowns mit potenziell vielen Optionen. Statt
 * einer vollständig im Browser gehaltenen Liste wird bei jeder Eingabe
 * (debounced) eine JSON-Suche gegen den Server geschickt (siehe die
 * jeweiligen `.../suche`-Routen, z. B. CustomerController::search()).
 *
 * Markup-Konvention (siehe App\Infrastructure\Http\renderLiveSearchField()):
 *   <div data-live-search data-url="..." data-mode="select|freetext" data-min-chars="1">
 *     <input data-live-search-input ...>
 *     <input type="hidden" data-live-search-value name="..." value="...">  (nur im "select"-Modus)
 *     <div data-live-search-results class="hidden"></div>
 *   </div>
 *
 * Zwei Modi:
 * - "select" (Standard): das Textfeld dient nur der Suche/Anzeige, der
 *   tatsächlich abgeschickte Wert ist die ID im versteckten Feld. Wird das
 *   Textfeld verlassen, ohne dass ein Vorschlag ausgewählt wurde, springt
 *   die Anzeige auf die zuletzt bestätigte Auswahl zurück (reines
 *   Auswahlfeld, kein Freitext — siehe Aufgabenstellung: "nur Auswahl
 *   bestehender Datensätze").
 * - "freetext": das Textfeld selbst ist das abgeschickte Feld (kein
 *   verstecktes Feld), ein Vorschlag füllt das Feld nur aus — beliebiger
 *   Text bleibt weiterhin möglich (z. B. Veranstaltungsort, siehe
 *   App\Application\Common\VenueResolver, das einen nicht zugeordneten
 *   Freitext weiterhin akzeptiert).
 *
 * Bewusst als EINE globale, gemeinsam genutzte Datei (anders als das
 * sonst in diesem Projekt für Formular-Hilfsfunktionen übliche
 * Dupliziert-pro-View-Muster, siehe z. B. renderDocumentClosingTextField())
 * — diese Komponente wird von sehr vielen, unterschiedlichen Formularen für
 * fünf verschiedene Entitätstypen genutzt; eine Duplizierung wäre hier
 * Wartungslast ohne Gegenwert. Zweites Vorbild für eine eigenständige JS-
 * Datei (statt eingebettetem <script>): public/assets/js/barcode-scanner.js.
 */
(function () {
    'use strict';

    var DEBOUNCE_MS = 250;

    function debounce(fn, delay) {
        var timer = null;

        return function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, delay);
        };
    }

    function setup(root) {
        var toolbarButtonClass = 'flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800';

        var input = root.querySelector('[data-live-search-input]');
        var hidden = root.querySelector('[data-live-search-value]');
        var resultsEl = root.querySelector('[data-live-search-results]');
        var url = root.getAttribute('data-url');

        if (!input || !resultsEl || !url) {
            return;
        }

        var mode = root.getAttribute('data-mode') || 'select';
        var minChars = parseInt(root.getAttribute('data-min-chars') || '1', 10);

        // Zuletzt bestätigte Auswahl (Anzeigetext + ID) — im "select"-Modus
        // das, worauf das Textfeld (und das versteckte ID-Feld) bei
        // Verlassen ohne neue Auswahl zurückspringen.
        var confirmedLabel = input.value;
        var confirmedId = hidden ? hidden.value : '';
        var items = [];
        var highlightedIndex = -1;
        var activeRequest = null;

        function hideResults() {
            resultsEl.classList.add('hidden');
            resultsEl.innerHTML = '';
            items = [];
            highlightedIndex = -1;
        }

        function highlight(index) {
            var buttons = resultsEl.querySelectorAll('[data-live-search-option]');
            buttons.forEach(function (button, i) {
                button.classList.toggle('bg-slate-100', i === index);
                button.classList.toggle('dark:bg-slate-800', i === index);
            });
            highlightedIndex = index;
        }

        function selectItem(item) {
            input.value = item.label;
            confirmedLabel = item.label;
            confirmedId = item.id;
            if (hidden) {
                hidden.value = item.id;
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }
            // Auch auf dem sichtbaren Textfeld 'input'/'change' feuern (z. B.
            // für externe Listener wie die Verteiler-Warnung in
            // resources/views/rentals/form.php, die direkt am
            // #event_location-Feld hängt und sonst nach einer Auswahl aus
            // den Vorschlägen nicht aktualisiert würde).
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            hideResults();
        }

        function renderResults(fetchedItems) {
            items = fetchedItems;
            resultsEl.innerHTML = '';

            if (items.length === 0) {
                var empty = document.createElement('div');
                empty.className = 'px-3 py-2 text-sm text-slate-400 dark:text-slate-500';
                empty.textContent = 'Keine Treffer.';
                resultsEl.appendChild(empty);
                resultsEl.classList.remove('hidden');
                highlightedIndex = -1;

                return;
            }

            items.forEach(function (item, index) {
                var button = document.createElement('button');
                button.type = 'button';
                button.setAttribute('data-live-search-option', '');
                button.className = toolbarButtonClass;

                var labelSpan = document.createElement('span');
                labelSpan.className = 'truncate';
                labelSpan.textContent = item.label;
                button.appendChild(labelSpan);

                if (item.sublabel) {
                    var subSpan = document.createElement('span');
                    subSpan.className = 'shrink-0 text-xs text-slate-400 dark:text-slate-500';
                    subSpan.textContent = item.sublabel;
                    button.appendChild(subSpan);
                }

                // mousedown statt click: läuft VOR dem blur-Handler des
                // Textfelds, damit die Auswahl nicht durch das
                // Zurückspringen auf confirmedLabel im blur-Handler
                // überschrieben wird.
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                    selectItem(item);
                });

                resultsEl.appendChild(button);
            });

            resultsEl.classList.remove('hidden');
            highlight(-1);
        }

        var runSearch = debounce(function (query) {
            if (activeRequest) {
                activeRequest.abort();
            }

            var controller = new AbortController();
            activeRequest = controller;

            fetch(url + '?q=' + encodeURIComponent(query), {
                headers: { 'Accept': 'application/json' },
                signal: controller.signal,
            })
                .then(function (response) { return response.ok ? response.json() : { items: [] }; })
                .then(function (data) {
                    if (activeRequest === controller) {
                        renderResults(data.items || []);
                    }
                })
                .catch(function (error) {
                    if (error.name !== 'AbortError') {
                        hideResults();
                    }
                });
        }, DEBOUNCE_MS);

        input.addEventListener('input', function () {
            var value = input.value;

            if (mode === 'select' && hidden) {
                // Getippter Text weicht von der zuletzt bestätigten Auswahl
                // ab — die alte ID ist nicht mehr gültig, bis neu
                // ausgewählt wird.
                hidden.value = '';
            }

            if (value.trim().length < minChars) {
                hideResults();

                return;
            }

            runSearch(value.trim());
        });

        input.addEventListener('keydown', function (event) {
            if (resultsEl.classList.contains('hidden') || items.length === 0) {
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                highlight(Math.min(highlightedIndex + 1, items.length - 1));
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                highlight(Math.max(highlightedIndex - 1, 0));
            } else if (event.key === 'Enter') {
                if (highlightedIndex >= 0 && items[highlightedIndex]) {
                    event.preventDefault();
                    selectItem(items[highlightedIndex]);
                }
            } else if (event.key === 'Escape') {
                hideResults();
            }
        });

        input.addEventListener('focusout', function (event) {
            // Klick auf einen Vorschlag läuft über mousedown (siehe oben)
            // und hat die Auswahl bereits verarbeitet, bevor dieses Event
            // feuert — hier nur noch das Zurückspringen im "select"-Modus,
            // falls ohne Auswahl verlassen wurde.
            window.setTimeout(function () {
                if (root.contains(document.activeElement)) {
                    return;
                }

                hideResults();

                if (mode === 'select' && input.value !== confirmedLabel) {
                    input.value = confirmedLabel;
                    if (hidden) {
                        hidden.value = confirmedId;
                    }
                }
            }, 0);
        });

        // Externes Setzen von außerhalb des Widgets (z. B. nach Anlage
        // eines neuen Datensatzes über ein "+ Neu"-Quick-Create-Modal, siehe
        // resources/views/customers/_quick_create_modal.php). Direktes
        // Setzen von input.value/hidden.value von außen würde beim nächsten
        // Verlassen des Feldes durch den focusout-Handler oben wieder auf
        // den alten confirmedLabel/confirmedId zurückspringen — deshalb hier
        // als eigenes Event, das auch den internen bestätigten Zustand
        // aktualisiert.
        root.addEventListener('livesearch:set', function (event) {
            var detail = event.detail || {};
            selectItem({ id: detail.id, label: detail.label });
        });

        root.setAttribute('data-live-search-ready', '1');
    }

    function init(root) {
        (root || document).querySelectorAll('[data-live-search]:not([data-live-search-ready])').forEach(setup);
    }

    window.LiveSearch = { init: init };

    document.addEventListener('DOMContentLoaded', function () { init(document); });
})();
