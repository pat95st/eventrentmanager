// Anordnungs-Editor der Startseite (resources/views/dashboard/_layout_editor.php).
// Kacheln lassen sich zwischen den Bereichen ziehen; für Tastatur und
// Touch-Geräte gibt es je Kachel ein Auswahlfeld (Bereich) und Pfeile
// (Reihenfolge). Gespeichert wird die Reihenfolge im Formular: je Kachel ein
// verstecktes order[]-Feld und das Auswahlfeld zone[<Schlüssel>].
(function () {
  'use strict';

  document.querySelectorAll('[data-layout-editor]').forEach(function (editor) {
    var zones = Array.prototype.slice.call(editor.querySelectorAll('[data-zone]'));
    var dragged = null;

    function zoneOf(tile) {
      return tile.closest('[data-zone]');
    }

    // Leerraum-Textknoten entfernen, damit eine leere Liste per CSS (:empty)
    // als Ablagefläche erkennbar ist.
    function tidy() {
      zones.forEach(function (zone) {
        Array.prototype.slice.call(zone.childNodes).forEach(function (node) {
          if (node.nodeType === Node.TEXT_NODE) {
            zone.removeChild(node);
          }
        });
        var tiles = zone.querySelectorAll('[data-tile]');
        tiles.forEach(function (tile, index) {
          tile.querySelector('select').value = zone.dataset.zone;
          tile.querySelector('[data-move="up"]').disabled = index === 0;
          tile.querySelector('[data-move="down"]').disabled = index === tiles.length - 1;
        });
      });
    }

    // Die Kachel, vor der an der Zeigerposition eingefügt wird (null = ans Ende).
    function tileAfterPointer(zone, x, y) {
      var multiColumn = getComputedStyle(zone).gridTemplateColumns.split(' ').length > 1;
      var tiles = zone.querySelectorAll('[data-tile]:not(.is-dragging)');
      for (var i = 0; i < tiles.length; i++) {
        var rect = tiles[i].getBoundingClientRect();
        var before = multiColumn
          ? y < rect.top || (y <= rect.bottom && x < rect.left + rect.width / 2)
          : y < rect.top + rect.height / 2;
        if (before) {
          return tiles[i];
        }
      }
      return null;
    }

    editor.querySelectorAll('[data-grip], [data-move]').forEach(function (el) {
      el.hidden = false;
    });

    editor.addEventListener('dragstart', function (event) {
      var tile = event.target.closest ? event.target.closest('[data-tile]') : null;
      if (!tile) {
        return;
      }
      dragged = tile;
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', '');
      // Erst nach dem Start markieren, sonst übernimmt das Zieh-Abbild die Transparenz.
      setTimeout(function () { tile.classList.add('is-dragging'); }, 0);
    });

    editor.addEventListener('dragend', function () {
      if (dragged) {
        dragged.classList.remove('is-dragging');
      }
      dragged = null;
      zones.forEach(function (zone) { (zone.closest('section') || zone).classList.remove('is-drop-target'); });
      tidy();
    });

    zones.forEach(function (zone) {
      // Ablagefläche ist der ganze Kasten des Bereichs, nicht nur die Liste darin.
      var area = zone.closest('section') || zone;
      area.addEventListener('dragover', function (event) {
        if (!dragged) {
          return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        zones.forEach(function (other) { (other.closest('section') || other).classList.toggle('is-drop-target', other === zone); });
        var next = tileAfterPointer(zone, event.clientX, event.clientY);
        if (next !== dragged.nextElementSibling || zoneOf(dragged) !== zone) {
          zone.insertBefore(dragged, next);
        }
      });
      area.addEventListener('drop', function (event) {
        event.preventDefault();
      });
    });

    editor.addEventListener('change', function (event) {
      if (!(event.target instanceof HTMLSelectElement)) {
        return;
      }
      var tile = event.target.closest('[data-tile]');
      var target = editor.querySelector('[data-zone="' + event.target.value + '"]');
      if (tile && target && zoneOf(tile) !== target) {
        target.appendChild(tile);
        tidy();
        event.target.focus();
      }
    });

    editor.addEventListener('click', function (event) {
      var button = event.target.closest ? event.target.closest('[data-move]') : null;
      if (!button) {
        return;
      }
      var tile = button.closest('[data-tile]');
      var zone = zoneOf(tile);
      if (button.dataset.move === 'up' && tile.previousElementSibling) {
        zone.insertBefore(tile, tile.previousElementSibling);
      } else if (button.dataset.move === 'down' && tile.nextElementSibling) {
        zone.insertBefore(tile.nextElementSibling, tile);
      }
      tidy();
      // Der Pfeil kann nach dem Verschieben deaktiviert sein – dann auf den anderen wechseln.
      (button.disabled ? tile.querySelector('[data-move]:not(:disabled)') || tile.querySelector('select') : button).focus();
    });

    tidy();
  });
})();
