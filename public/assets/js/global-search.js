/**
 * Global search (Ctrl+K / Cmd+K).
 *
 * Opens the dialog from resources/views/partials/global_search.php, asks
 * GET /suche?q=... (GlobalSearchController) while the user types and lists
 * the hits grouped by type, each with the icon of its type. Arrow keys move
 * through the list, Enter opens the selected hit, Escape closes.
 *
 * Motion (see also public/assets/css/global-search.css): opening and closing
 * are CSS transitions driven by the class "gs-open", so pressing the shortcut
 * again while the dialog is still closing simply reverses the motion from
 * where it is. The panel grows out of the control that opened it, and its
 * height follows the content instead of jumping. Loading hints are delayed
 * so that fast searches do not flicker.
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

    // Short pause so that a burst of key presses is one request; stale
    // requests are aborted anyway.
    var DEBOUNCE_MS = 90;
    // Longest exit transition in the stylesheet.
    var CLOSE_MS = 150;
    // A search that answers faster than this shows no loading hint at all.
    var LOADING_HINT_MS = 250;

    var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var panel = dialog.querySelector('.gs-panel');
    var body = dialog.querySelector('.gs-body');
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
    var closeTimer = null;
    var loadingTimer = null;

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

    function isOpen() {
        return dialog.open && dialog.classList.contains('gs-open');
    }

    /**
     * @param {Element=} source control that opened the dialog; the panel
     *     grows out of it (and shrinks back into it on close)
     */
    function open(source) {
        clearTimeout(closeTimer);
        if (!dialog.open) {
            returnFocusTo = document.activeElement;
            dialog.showModal();
            var origin = '50% 0';
            if (source && source.getBoundingClientRect) {
                var rect = source.getBoundingClientRect();
                if (rect.width > 0) {
                    origin = (rect.left + rect.width / 2 - panel.offsetLeft) + 'px ' + (rect.top + rect.height / 2 - panel.offsetTop) + 'px';
                }
            }
            panel.style.transformOrigin = origin;
            void panel.offsetWidth; // commit the closed state so that the transition runs
            input.select();
        }
        // Also reverses a close that is still running, from its current state.
        dialog.classList.add('gs-open');
        input.focus();
    }

    function close() {
        if (!isOpen()) {
            return;
        }
        dialog.classList.remove('gs-open');
        clearTimeout(closeTimer);
        closeTimer = setTimeout(function () {
            if (dialog.open && !dialog.classList.contains('gs-open')) {
                dialog.close();
            }
        }, CLOSE_MS);
    }

    /**
     * Changes the content of the result area and lets its height follow
     * smoothly, starting from the height it currently has on screen.
     */
    function swap(render) {
        if (reducedMotion || !dialog.open) {
            render();
            return;
        }
        var from = body.offsetHeight;
        body.style.height = '';
        render();
        var to = body.offsetHeight;
        if (from === to) {
            body.classList.remove('gs-resizing');
            return;
        }
        body.classList.add('gs-resizing');
        body.style.height = from + 'px';
        void body.offsetHeight;
        body.style.height = to + 'px';
    }

    body.addEventListener('transitionend', function (event) {
        if (event.target === body && event.propertyName === 'height') {
            body.style.height = '';
            body.classList.remove('gs-resizing');
        }
    });

    // Soft edge under the field only while the list is scrolled under it.
    body.addEventListener('scroll', function () {
        panel.classList.toggle('gs-scrolled', body.scrollTop > 2);
    }, { passive: true });

    function setLoading(on) {
        clearTimeout(loadingTimer);
        if (on) {
            loadingTimer = setTimeout(function () {
                spinner.hidden = false;
                resultsEl.classList.add('gs-stale');
            }, LOADING_HINT_MS);
            return;
        }
        spinner.hidden = true;
        resultsEl.classList.remove('gs-stale');
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
                body.scrollTop = 0;
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

        // Highlight on hover and already on press (touch), not only on release.
        var activate = function () {
            if (activeIndex !== index) {
                setActive(index, false);
            }
        };
        link.addEventListener('mousemove', activate);
        link.addEventListener('pointerdown', activate);

        return link;
    }

    function renderResults(items, term) {
        shownTerm = term;
        if (items.length === 0) {
            swap(function () {
                message.textContent = text(config.labels.empty, { term: term });
                show('message');
            });
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

        swap(function () {
            show('results');
            resultsEl.textContent = '';
            resultsEl.appendChild(fragment);
        });
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
                swap(function () {
                    message.textContent = config.labels.error;
                    show('message');
                });
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
            if (intro.hidden) {
                swap(function () { show('intro'); });
            }
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

    // Press on the dimmed area around the panel closes.
    dialog.addEventListener('pointerdown', function (event) {
        if (event.target === dialog) {
            close();
        }
    });

    // Escape: animate out like every other way of closing.
    dialog.addEventListener('cancel', function (event) {
        event.preventDefault();
        close();
    });

    dialog.addEventListener('close', function () {
        dialog.classList.remove('gs-open');
        panel.classList.remove('gs-scrolled');
        body.style.height = '';
        body.classList.remove('gs-resizing');
        if (returnFocusTo && typeof returnFocusTo.focus === 'function' && document.contains(returnFocusTo)) {
            returnFocusTo.focus();
        }
        returnFocusTo = null;
    });

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest ? event.target.closest('[data-global-search-open]') : null;
        if (trigger) {
            event.preventDefault();
            open(trigger);
        }
    });

    document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && (event.key === 'k' || event.key === 'K')) {
            // Also keeps the browser from focusing its own search/address bar.
            event.preventDefault();
            if (isOpen()) {
                close();
            } else {
                open();
            }
        }
    });

    window.globalSearch = { open: open, close: close };
})();
