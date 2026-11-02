/**
 * Tabellen per Klick auf die Spaltenüberschrift sortieren (Nutzerwunsch).
 *
 * Gilt automatisch für jede <table> mit <thead> und <tbody>, auch für per
 * AJAX nachgeladene Listen (Event-Delegation, keine Initialisierung nötig).
 * Erneuter Klick dreht die Richtung um, ein dritter stellt die ursprüngliche
 * Reihenfolge wieder her. Sortiert wird nur im Browser, d. h. bei Listen mit
 * Seitenumschaltung nur die aktuell angezeigte Seite.
 *
 * Nicht sortiert werden:
 *  - Tabellen mit data-no-sort (z. B. Kalender) und Spalten mit data-no-sort
 *    oder ohne Überschrift (Aktionen),
 *  - Tabellen mit Eingabefeldern in den Zeilen (Formular-Positionen),
 *  - Tabellen, deren Zeilen nicht alle gleich viele Zellen haben
 *    (Gruppenüberschriften, "Keine Einträge", aufklappbare Detailzeilen).
 *
 * Eine Zelle kann mit data-sort-value="…" einen eigenen Sortierwert angeben.
 * Erkannt werden Datum (TT.MM.JJJJ [HH:MM]), Zahlen/Beträge im deutschen
 * Format (1.234,56 €, 12 %, 3 Stk.) und sonst Text (natürliche Sortierung).
 */
(function () {
    'use strict';

    var collator = new Intl.Collator('de', { numeric: true, sensitivity: 'base' });

    function headerCells(table) {
        var row = table.tHead && table.tHead.rows[table.tHead.rows.length - 1];
        return row ? Array.prototype.slice.call(row.cells) : [];
    }

    function bodyRows(table) {
        var rows = [];
        Array.prototype.forEach.call(table.tBodies, function (tbody) {
            Array.prototype.forEach.call(tbody.rows, function (row) { rows.push(row); });
        });
        return rows;
    }

    function columnIndex(th) {
        var index = 0;
        var cell = th.previousElementSibling;
        while (cell) { index += cell.colSpan || 1; cell = cell.previousElementSibling; }
        return index;
    }

    function cellAt(row, index) {
        var position = 0;
        for (var i = 0; i < row.cells.length; i++) {
            if (position === index) { return row.cells[i]; }
            position += row.cells[i].colSpan || 1;
            if (position > index) { return null; }
        }
        return null;
    }

    function width(row) {
        var total = 0;
        for (var i = 0; i < row.cells.length; i++) { total += row.cells[i].colSpan || 1; }
        return total;
    }

    function isSortable(table, th) {
        if (!table || table.hasAttribute('data-no-sort') || th.hasAttribute('data-no-sort')) { return false; }
        if (th.textContent.trim() === '' || (th.colSpan || 1) > 1 || table.tBodies.length !== 1) { return false; }
        var rows = bodyRows(table);
        if (rows.length < 2) { return false; }
        var columns = width(table.tHead.rows[table.tHead.rows.length - 1]);
        for (var i = 0; i < rows.length; i++) {
            if (width(rows[i]) !== columns) { return false; }
        }
        return !table.tBodies[0].querySelector('input:not([type="hidden"]):not([type="submit"]):not([type="button"]), select, textarea, [draggable="true"]');
    }

    function sortKey(cell) {
        if (!cell) { return { type: 2, value: '' }; }
        var raw = cell.hasAttribute('data-sort-value') ? cell.getAttribute('data-sort-value') : cell.textContent;
        var text = raw.replace(/\s+/g, ' ').trim();
        if (text === '' || text === '–' || text === '-' || text === '—') { return { type: 3, value: '' }; }

        var date = text.match(/^(\d{1,2})\.(\d{1,2})\.(\d{4})(?:,?\s+(\d{1,2}):(\d{2}))?/);
        if (date) {
            return { type: 0, value: Date.UTC(+date[3], +date[2] - 1, +date[1], +(date[4] || 0), +(date[5] || 0)) };
        }
        var iso = text.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (iso) {
            return { type: 0, value: Date.UTC(+iso[1], +iso[2] - 1, +iso[3]) };
        }
        // Zahl/Betrag: optional Vorzeichen, Tausenderpunkte, Dezimalkomma, danach höchstens eine kurze Einheit
        var number = text.match(/^([+\-−]?)\s*(\d{1,3}(?:\.\d{3})+|\d+)(?:,(\d+))?\s*(?:€|EUR|%|[A-Za-zÄÖÜäöüß.×]{1,6})?$/);
        if (number) {
            var value = parseFloat(number[2].replace(/\./g, '') + (number[3] ? '.' + number[3] : ''));
            return { type: 1, value: number[1] === '-' || number[1] === '−' ? -value : value };
        }
        return { type: 2, value: text };
    }

    function compare(a, b) {
        if (a.type === 3 || b.type === 3) { return (a.type === 3) - (b.type === 3); } // Leere immer ans Ende
        if (a.type !== b.type) { return a.type - b.type; }
        if (a.type === 2) { return collator.compare(a.value, b.value); }
        return a.value - b.value;
    }

    function indicate(table, activeTh, direction) {
        headerCells(table).forEach(function (th) {
            var marker = th.querySelector('[data-sort-indicator]');
            if (th !== activeTh || direction === null) {
                if (marker) { marker.remove(); }
                th.removeAttribute('aria-sort');
                return;
            }
            if (!marker) {
                marker = document.createElement('span');
                marker.setAttribute('data-sort-indicator', '');
                marker.setAttribute('aria-hidden', 'true');
                marker.style.marginLeft = '0.25rem';
                th.appendChild(marker);
            }
            marker.textContent = direction === 'asc' ? '▲' : '▼';
            th.setAttribute('aria-sort', direction === 'asc' ? 'ascending' : 'descending');
        });
    }

    function sort(table, th) {
        var tbody = table.tBodies[0];
        var rows = bodyRows(table);
        rows.forEach(function (row, i) { if (!row.hasAttribute('data-sort-original')) { row.setAttribute('data-sort-original', String(i)); } });

        var index = columnIndex(th);
        var previous = table.__sortState || {};
        var direction = previous.index !== index ? 'asc' : (previous.direction === 'asc' ? 'desc' : (previous.direction === 'desc' ? null : 'asc'));
        table.__sortState = { index: index, direction: direction };

        var keyed = rows.map(function (row) {
            return { row: row, key: sortKey(cellAt(row, index)), original: +row.getAttribute('data-sort-original') };
        });
        keyed.sort(function (a, b) {
            if (direction === null) { return a.original - b.original; }
            var result = compare(a.key, b.key);
            if (direction === 'desc' && a.key.type !== 3 && b.key.type !== 3) { result = -result; }
            return result || a.original - b.original;
        });
        keyed.forEach(function (entry) { tbody.appendChild(entry.row); });
        indicate(table, th, direction);
    }

    document.addEventListener('click', function (event) {
        var th = event.target.closest('thead th');
        if (!th || event.target.closest('a, button, input, select, label')) { return; }
        var table = th.closest('table');
        if (!isSortable(table, th)) { return; }
        sort(table, th);
    });

    // Sortierbare Überschriften als klickbar kennzeichnen (Cursor + Tooltip).
    document.addEventListener('mouseover', function (event) {
        var th = event.target.closest('thead th');
        if (!th || th.hasAttribute('data-sort-checked')) { return; }
        th.setAttribute('data-sort-checked', '');
        if (isSortable(th.closest('table'), th)) {
            th.style.cursor = 'pointer';
            th.style.userSelect = 'none';
            if (!th.title) { th.title = 'Klicken zum Sortieren'; }
        }
    });
})();
