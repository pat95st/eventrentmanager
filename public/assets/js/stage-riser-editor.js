// Riser auf dem Bühnenplan (resources/views/venues/_stage_risers.php):
//   1. Riser per Maus/Finger oder Pfeiltasten verschieben; die neue Position
//      wird sofort gespeichert (POST …/riser/{id}/position, JSON-Antwort).
//   2. Riser-Formulare: Maßfelder passend zur Form ein-/ausblenden.
// Koordinaten im SVG sind Meter; gespeichert wird der Mittelpunkt des Risers.
(function () {
  'use strict';

  var DRAG_STEP = 0.05; // Raster beim Ziehen: 5 cm
  var KEY_STEP = 0.1;   // Pfeiltasten: 10 cm, mit Umschalt 50 cm

  function clamp(value, min, max) { return Math.min(max, Math.max(min, value)); }
  function snap(value, step) { return Math.round(value / step) * step; }
  function formatMeters(value) { return String(Math.round(value * 100) / 100).replace('.', ','); }

  document.querySelectorAll('[data-riser-plan]').forEach(function (svg) {
    var stageWidth = parseFloat(svg.dataset.stageWidth);
    var stageDepth = parseFloat(svg.dataset.stageDepth);
    var container = svg.parentElement;
    var status = container.querySelector('[data-riser-status]');

    function toStage(event) {
      var point = svg.createSVGPoint();
      point.x = event.clientX;
      point.y = event.clientY;
      return point.matrixTransform(svg.getScreenCTM().inverse());
    }

    function place(group, x, y) {
      group.dataset.x = x.toFixed(2);
      group.dataset.y = y.toFixed(2);
      group.setAttribute('transform', 'translate(' + x.toFixed(2) + ' ' + y.toFixed(2) + ')');
    }

    function showSaved(id, x, y) {
      var position = container.querySelector('[data-riser-position="' + id + '"]');
      if (position) {
        position.textContent = position.dataset.template.replace('{x}', formatMeters(x)).replace('{y}', formatMeters(y));
      }
      var form = container.querySelector('[data-riser-edit="' + id + '"]');
      if (form) {
        form.querySelector('[data-riser-center-x]').value = formatMeters(x);
        form.querySelector('[data-riser-center-y]').value = formatMeters(y);
      }
    }

    function save(group, previous) {
      var x = parseFloat(group.dataset.x);
      var y = parseFloat(group.dataset.y);
      if (status) { status.textContent = ''; }

      fetch(svg.dataset.moveUrl + '/' + encodeURIComponent(group.dataset.riser) + '/position', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-CSRF-Token': window.CSRF_TOKEN || '',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json'
        },
        body: 'center_x=' + x.toFixed(2) + '&center_y=' + y.toFixed(2)
      }).then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (data) {
          if (!response.ok || !data.ok) { throw new Error(data.error || svg.dataset.msgFailed); }
          group.dataset.savedX = x.toFixed(2);
          group.dataset.savedY = y.toFixed(2);
          showSaved(group.dataset.riser, x, y);
          if (status) { status.textContent = svg.dataset.msgMoved; }
        });
      }).catch(function (error) {
        // Nicht gespeichert: zurück an die letzte gespeicherte Stelle, damit der Plan nicht lügt.
        place(group, previous.x, previous.y);
        if (status) { status.textContent = error.message || svg.dataset.msgFailed; }
      });
    }

    svg.querySelectorAll('[data-riser]').forEach(function (group) {
      group.dataset.savedX = group.dataset.x;
      group.dataset.savedY = group.dataset.y;
      var drag = null;
      var keyTimer = null;

      function saved() { return { x: parseFloat(group.dataset.savedX), y: parseFloat(group.dataset.savedY) }; }

      group.addEventListener('pointerdown', function (event) {
        if (event.button !== undefined && event.button !== 0) { return; }
        var pointer = toStage(event);
        drag = { dx: pointer.x - parseFloat(group.dataset.x), dy: pointer.y - parseFloat(group.dataset.y) };
        // Beim Ziehen über den anderen Risern. Erst umhängen, dann den Zeiger
        // binden — ein Umhängen danach würde die Bindung wieder lösen.
        if (svg.lastElementChild !== group) { svg.appendChild(group); }
        group.setPointerCapture(event.pointerId);
        event.preventDefault();
      });

      group.addEventListener('pointermove', function (event) {
        if (!drag) { return; }
        var pointer = toStage(event);
        place(
          group,
          clamp(snap(pointer.x - drag.dx, DRAG_STEP), 0, stageWidth),
          clamp(snap(pointer.y - drag.dy, DRAG_STEP), 0, stageDepth)
        );
      });

      function endDrag(event) {
        if (!drag) { return; }
        drag = null;
        if (group.hasPointerCapture(event.pointerId)) { group.releasePointerCapture(event.pointerId); }
        var before = saved();
        if (parseFloat(group.dataset.x) !== before.x || parseFloat(group.dataset.y) !== before.y) {
          save(group, before);
        }
      }
      group.addEventListener('pointerup', endDrag);
      group.addEventListener('pointercancel', endDrag);

      group.addEventListener('keydown', function (event) {
        var moves = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
        var move = moves[event.key];
        if (!move) { return; }
        event.preventDefault();
        var step = event.shiftKey ? KEY_STEP * 5 : KEY_STEP;
        place(
          group,
          clamp(snap(parseFloat(group.dataset.x) + move[0] * step, 0.01), 0, stageWidth),
          clamp(snap(parseFloat(group.dataset.y) + move[1] * step, 0.01), 0, stageDepth)
        );
        // Erst speichern, wenn die Tasten kurz ruhen — nicht bei jedem Tastendruck.
        clearTimeout(keyTimer);
        keyTimer = setTimeout(function () {
          var before = saved();
          if (parseFloat(group.dataset.x) !== before.x || parseFloat(group.dataset.y) !== before.y) {
            save(group, before);
          }
        }, 500);
      });
    });
  });

  document.querySelectorAll('[data-riser-form]').forEach(function (form) {
    var shape = form.querySelector('[data-riser-shape]');
    var depth = form.querySelector('[data-riser-depth]');
    var widthLabel = form.querySelector('[data-riser-width-label]');
    var withoutDepth = (form.dataset.shapesWithoutDepth || '').split(' ');

    function sync() {
      var hasDepth = withoutDepth.indexOf(shape.value) === -1;
      depth.classList.toggle('hidden', !hasDepth);
      depth.querySelector('input').required = hasDepth;
      widthLabel.textContent = hasDepth ? widthLabel.dataset.labelWidth : widthLabel.dataset.labelDiameter;
    }

    shape.addEventListener('change', sync);
    sync();
  });
})();
