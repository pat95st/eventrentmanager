/**
 * Weiches Ein- und Ausblenden aller Modals (Nutzerwunsch).
 *
 * Zwei Arten von Modals gibt es in der App:
 *  1. Overlays als <div class="fixed inset-0 …">, die per Klasse "hidden"
 *     ein-/ausgeblendet werden (Vorlagen-, Artikel-, Kunden-Schnellanlage,
 *     dynamisch erzeugte wie die Set-Auswahl). Ein MutationObserver bemerkt
 *     das Umschalten: Beim Einblenden wird von Deckkraft 0 auf 1 übergeblendet,
 *     beim Ausblenden wird "hidden" erst nach der Überblendung wieder gesetzt.
 *     Entfernt ein Skript das Overlay direkt aus dem DOM, kann es nicht mehr
 *     ausgeblendet werden – dafür gibt es window.ModalFade.remove(el).
 *  2. Native <dialog>-Elemente: Einblenden per CSS-Animation (siehe
 *     <style> in layout.php), Ausblenden über ein umhülltes
 *     HTMLDialogElement.close(); Escape läuft über denselben Weg.
 *
 * Bei "Bewegung reduzieren" im Betriebssystem bleibt alles wie bisher.
 */
(function () {
    'use strict';

    var DURATION = 180;
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduced) {
        window.ModalFade = { remove: function (el) { el.remove(); } };
        return;
    }

    function isOverlay(el) {
        return el && el.nodeType === 1 && el.classList.contains('fixed') && el.classList.contains('inset-0');
    }

    var observer;
    var DISPLAY_CLASSES = ['flex', 'grid', 'block'];

    // Eigene Klassenänderungen dürfen den Observer nicht erneut auslösen:
    // nach jeder eigenen Änderung werden die dadurch entstandenen Einträge
    // abgeholt und alle fremden davon normal verarbeitet.
    function quietly(el, work) {
        work();
        var pending = observer.takeRecords().filter(function (record) { return record.target !== el; });
        if (pending.length) { handle(pending); }
    }

    function fadeIn(el) {
        clearTimeout(el.__modalFadeTimer);
        el.__modalFadeTimer = null;
        quietly(el, function () {
            // Anzeige-Klassen merken: Skripte entfernen beim Schließen oft
            // "flex" vor dem Setzen von "hidden" – ohne sie würde das
            // Overlay während des Ausblendens als Block dargestellt.
            el.__modalFadeDisplay = DISPLAY_CLASSES.filter(function (c) { return el.classList.contains(c); });
            el.classList.add('modal-fade', 'modal-fade-out');
            void el.offsetWidth; // Startzustand übernehmen, damit die Transition greift
            el.classList.remove('modal-fade-out');
        });
    }

    function fadeOut(el, done) {
        clearTimeout(el.__modalFadeTimer);
        var restored = (el.__modalFadeDisplay || []).filter(function (c) { return !el.classList.contains(c); });
        quietly(el, function () {
            el.classList.remove('hidden');
            restored.forEach(function (c) { el.classList.add(c); });
            el.classList.add('modal-fade');
            void el.offsetWidth;
            el.classList.add('modal-fade-out');
        });
        el.__modalFadeTimer = setTimeout(function () {
            el.__modalFadeTimer = null;
            quietly(el, function () {
                if (done) {
                    done();
                } else {
                    el.classList.add('hidden');
                    el.classList.remove('modal-fade-out');
                    restored.forEach(function (c) { el.classList.remove(c); });
                }
            });
        }, DURATION);
    }

    function handle(records) {
        // Pro Element zählt nur der Zustand vor dem ersten und nach dem
        // letzten Eintrag (Skripte ändern oft mehrere Klassen nacheinander).
        var firstOld = new Map();
        records.forEach(function (record) {
            if (record.type === 'childList') {
                Array.prototype.forEach.call(record.addedNodes, function (node) {
                    if (isOverlay(node) && !node.classList.contains('hidden')) { fadeIn(node); }
                });
                return;
            }
            if (isOverlay(record.target) && !firstOld.has(record.target)) {
                firstOld.set(record.target, record.oldValue || '');
            }
        });
        firstOld.forEach(function (oldValue, el) {
            var wasHidden = /(^|\s)hidden(\s|$)/.test(oldValue);
            var isHidden = el.classList.contains('hidden');
            if (wasHidden && !isHidden) {
                fadeIn(el);
            } else if (!wasHidden && isHidden) {
                fadeOut(el);
            } else if (!isHidden && el.__modalFadeTimer) {
                // Während des Ausblendens wieder geöffnet
                fadeIn(el);
            }
        });
    }

    observer = new MutationObserver(handle);
    observer.observe(document.documentElement, { subtree: true, childList: true, attributes: true, attributeFilter: ['class'], attributeOldValue: true });

    // Native <dialog>: Schließen erst nach der Ausblend-Animation.
    if (window.HTMLDialogElement) {
        var nativeClose = HTMLDialogElement.prototype.close;
        HTMLDialogElement.prototype.close = function (returnValue) {
            var dialog = this;
            var args = arguments;
            if (!dialog.open || dialog.__modalClosing) {
                return nativeClose.apply(dialog, args);
            }
            dialog.__modalClosing = true;
            dialog.classList.add('modal-closing');
            setTimeout(function () {
                dialog.__modalClosing = false;
                dialog.classList.remove('modal-closing');
                nativeClose.apply(dialog, args);
            }, DURATION);
        };
        document.addEventListener('cancel', function (event) {
            var dialog = event.target;
            if (dialog instanceof HTMLDialogElement && dialog.open) {
                event.preventDefault();
                dialog.close();
            }
        }, true);
    }

    window.ModalFade = {
        /** Overlay ausblenden und danach aus dem DOM entfernen. */
        remove: function (el) {
            if (!el || !el.isConnected) { return; }
            fadeOut(el, function () { el.remove(); });
        },
    };
})();
