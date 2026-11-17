/**
 * Global search (Ctrl+K / Cmd+K).
 *
 * Opens the dialog from resources/views/partials/global_search.php, asks
 * GET /suche?q=... (GlobalSearchController) while the user types and lists
 * the hits grouped by type, each with the icon of its type. Arrow keys move
 * through the list, Enter opens the selected hit, Escape closes.
 *
 * Configuration comes from window.GLOBAL_SEARCH (url, minChars, types with
 * their translated labels, message texts).
 */
(function () {
    'use strict';

    var config = window.GLOBAL_SEARCH;
    var dialog = document.getElementById('global-search');
    if (!config || !dialog || typeof dialog.showModal !== 'function') {
        return;
    }

    var DEBOUNCE_MS = 150;

    var input = dialog.querySelector('#global-search-input');
    var intro = dialog.querySelector('[data-gs-intro]');
    var message = dialog.querySelector('[data-gs-message]');
    var resultsEl = dialog.querySelector('[data-gs-results]');
    var spinner = dialog.querySelector('[data-gs-spinner]');
    var live = dialog.querySelector('[data-gs-live]');

    var typeLabels = {};
    config.types.forEach(function (type) { typeLabels[type.key] = type.label; });

    var options = [];       // <a role="option"> elements in display order
    var activeIndex = -1;
    var debounceTimer = null;
    var request = null;     // AbortController of the running request
    var shownTerm = '';     // term the visible results belong to
    var returnFocusTo = null;

    // Apple keyboards: show ⌘K instead of Ctrl+K on the header button.
    var isApple = /Mac|iPhone|iPad|iPod/.test(navigator.platform || '') || /Mac OS X/.test(navigator.userAgent || '');
    if (isApple) {
        document.querySelectorAll('[data-gs-modifier]').forEach(function (el) { el.textContent = '⌘'; });
        document.querySelectorAll('[data-gs-plus]').forEach(function (el) { el.remove(); });
        document.querySelectorAll('[data-global-search-open][title]').forEach(function (el) {
            el.title = el.title.replace(/\s*\([^)]*\)$/, ' (⌘K)');
        });
    }

    function text(template, values) {
        return template.replace(/\{(\w+)\}/g, function (match, key) {
            return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : match;
        });
    }

    function open() {
        if (dialog.open) {
            input.focus();
            return;
        }
        returnFocusTo = document.activeElement;
        dialog.showModal();
        input.focus();
        input.select();
    }

    function close() {
        if (dialog.open) {
            dialog.close();
        }
    }

    function setLoading(on) {
        spinner.hidden = !on;
        resultsEl.classList.toggle('gs-stale', on);
    }

    function show(part) {
        intro.hidden = part !== 'intro';
        message.hidden = part !== 'message';
        resultsEl.hidden = part !== 'results';
        input.setAttribute('aria-expanded', part === 'results' ? 'true' : 'false');
        if (part !== 'results') {
            options = [];
            activeIndex = -1;
            input.removeAttribute('aria-activedescendant');
            resultsEl.textContent = '';
        }
    }

    function setActive(index, scroll) {
        if (activeIndex >= 0 && options[activeIndex]) {
            options[activeIndex].setAttribute('aria-selected', 'false');
        }
        activeIndex = index;
        if (index < 0 || !options[index]) {
            input.removeAttribute('aria-activedescendant');
            return;
        }
        options[index].setAttribute('aria-selected', 'true');
        input.setAttribute('aria-activedescendant', options[index].id);
        if (scroll) {
            // The first hit sits below its group heading: keep that visible too.
            if (index === 0) {
                resultsEl.parentNode.scrollTop = 0;
            } else {
                options[index].scrollIntoView({ block: 'nearest' });
            }
        }
    }

    /** Appends $value to $parent, with the searched words emphasised. */
    function appendHighlighted(parent, value, words) {
        var lower = value.toLowerCase();
        var position = 0;
        while (position < value.length) {
            var next = -1;
            var length = 0;
            for (var i = 0; i < words.length; i++) {
                var found = lower.indexOf(words[i], position);
                if (found !== -1 && (next === -1 || found < next || (found === next && words[i].length > length))) {
                    next = found;
                    length = words[i].length;
                }
            }
            if (next === -1) {
                break;
            }
            if (next > position) {
                parent.appendChild(document.createTextNode(value.slice(position, next)));
            }
            var mark = document.createElement('mark');
            mark.textContent = value.slice(next, next + length);
            parent.appendChild(mark);
            position = next + length;
        }
        if (position < value.length) {
            parent.appendChild(document.createTextNode(value.slice(position)));
        }
    }

    function icon(type) {
        var holder = document.createElement('span');
        holder.className = 'gs-option-icon';
        var template = dialog.querySelector('template[data-gs-icon="' + type + '"]');
        if (template) {
            holder.appendChild(template.content.cloneNode(true));
        }
        return holder;
    }

    function renderOption(item, words, index) {
        var link = document.createElement('a');
        link.className = 'gs-option';
        link.id = 'global-search-option-' + index;
        link.href = item.url;
        link.setAttribute('role', 'option');
        link.setAttribute('aria-selected', 'false');
        link.tabIndex = -1;
        link.appendChild(icon(item.type));

        var body = document.createElement('span');
        body.className = 'gs-option-text';
        var title = document.createElement('span');
        title.className = 'gs-option-title';
        var titleText = document.createElement('span');
        appendHighlighted(titleText, item.title, words);
        title.appendChild(titleText);
        if (item.badge) {
            var badge = document.createElement('span');
            badge.className = 'gs-badge';
            badge.textContent = item.badge;
            title.appendChild(badge);
        }
        body.appendChild(title);
        if (item.subtitle) {
            var subtitle = document.createElement('span');
            subtitle.className = 'gs-option-subtitle';
            appendHighlighted(subtitle, item.subtitle, words);
            body.appendChild(subtitle);
        }
        link.appendChild(body);

        var enter = document.createElement('kbd');
        enter.className = 'gs-kbd gs-option-enter';
        enter.setAttribute('aria-hidden', 'true');
        enter.textContent = '↵';
        link.appendChild(enter);

        link.addEventListener('mousemove', function () {
            if (activeIndex !== index) {
                setActive(index, false);
            }
        });

        return link;
    }

    function renderResults(items, term) {
        shownTerm = term;
        if (items.length === 0) {
            message.textContent = text(config.labels.empty, { term: term });
            show('message');
            live.textContent = message.textContent;
            return;
        }

        var words = term.toLowerCase().split(/\s+/).filter(Boolean);
        var fragment = document.createDocumentFragment();
        var newOptions = [];
        var group = null;
        var groupType = null;

        items.forEach(function (item) {
            if (item.type !== groupType) {
                groupType = item.type;
                group = document.createElement('div');
                group.setAttribute('role', 'group');
                var heading = document.createElement('p');
                heading.className = 'gs-group-title';
                heading.id = 'global-search-group-' + item.type;
                heading.textContent = typeLabels[item.type] || item.type;
                group.setAttribute('aria-labelledby', heading.id);
                group.appendChild(heading);
                fragment.appendChild(group);
            }
            var option = renderOption(item, words, newOptions.length);
            newOptions.push(option);
            group.appendChild(option);
        });

        show('results');
        resultsEl.textContent = '';
        resultsEl.appendChild(fragment);
        options = newOptions;
        activeIndex = -1;
        setActive(0, true);
        live.textContent = text(config.labels.resultsCount, { count: String(items.length) });
    }

    function search(term) {
        if (request) {
            request.abort();
        }
        var controller = typeof AbortController === 'function' ? new AbortController() : null;
        request = controller;
        setLoading(true);

        fetch(config.url + '?q=' + encodeURIComponent(term), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller ? controller.signal : undefined
        })
            .then(function (response) {
                // An expired session answers with a redirect to the login page (HTML).
                var type = response.headers.get('Content-Type') || '';
                if (!response.ok || type.indexOf('application/json') === -1) {
                    throw new Error('search failed');
                }
                return response.json();
            })
            .then(function (data) {
                if (request !== controller) {
                    return;
                }
                request = null;
                setLoading(false);
                renderResults(Array.isArray(data.items) ? data.items : [], term);
            })
            .catch(function (error) {
                if ((error && error.name === 'AbortError') || request !== controller) {
                    return;
                }
                request = null;
                setLoading(false);
                shownTerm = '';
                message.textContent = config.labels.error;
                show('message');
                live.textContent = config.labels.error;
            });
    }

    function onInput() {
        clearTimeout(debounceTimer);
        var term = input.value.replace(/\s+/g, ' ').trim();

        if (term.length < config.minChars) {
            if (request) {
                request.abort();
                request = null;
            }
            setLoading(false);
            shownTerm = '';
            live.textContent = '';
            show('intro');
            return;
        }
        if (term === shownTerm && !request) {
            return;
        }
        debounceTimer = setTimeout(function () { search(term); }, DEBOUNCE_MS);
    }

    input.addEventListener('input', onInput);

    input.addEventListener('keydown', function (event) {
        if (event.isComposing) {
            return;
        }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (options.length === 0) {
                return;
            }
            var step = event.key === 'ArrowDown' ? 1 : -1;
            setActive((activeIndex + step + options.length) % options.length, true);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            var option = options[activeIndex];
            if (!option) {
                return;
            }
            if (event.ctrlKey || event.metaKey) {
                window.open(option.href, '_blank', 'noopener');
            } else {
                option.click();
            }
        }
    });

    // A click on a hit navigates; close the dialog so that it is not still
    // open when the user comes back via the browser's back button.
    resultsEl.addEventListener('click', function (event) {
        var link = event.target.closest ? event.target.closest('a.gs-option') : null;
        if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && event.button === 0) {
            close();
        }
    });

    dialog.querySelector('[data-gs-close]').addEventListener('click', close);

    // Click on the dimmed area around the panel closes.
    dialog.addEventListener('mousedown', function (event) {
        if (event.target === dialog) {
            close();
        }
    });

    dialog.addEventListener('close', function () {
        if (returnFocusTo && typeof returnFocusTo.focus === 'function' && document.contains(returnFocusTo)) {
            returnFocusTo.focus();
        }
        returnFocusTo = null;
    });

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest ? event.target.closest('[data-global-search-open]') : null;
        if (trigger) {
            event.preventDefault();
            open();
        }
    });

    document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && (event.key === 'k' || event.key === 'K')) {
            // Also keeps the browser from focusing its own search/address bar.
            event.preventDefault();
            if (dialog.open) {
                close();
            } else {
                open();
            }
        }
    });

    window.globalSearch = { open: open, close: close };
})();
