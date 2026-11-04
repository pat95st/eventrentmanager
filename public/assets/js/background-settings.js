// Einstellungen › Firma › Hintergründe (Migration 126): Vorschau live
// aktualisieren, während Deckkraft, Einpassung, Ausschnitt und Zoom
// verändert werden. Gespeichert wird erst mit „Darstellung speichern“.
(function () {
  'use strict';

  var DEFAULTS = { opacity: 100, fit: 'cover', position_x: 50, position_y: 50, zoom: 100 };

  document.querySelectorAll('form[data-bg-style]').forEach(function (form) {
    var layer = document.getElementById(form.getAttribute('data-bg-style') + '-layer');
    if (!layer) {
      return;
    }
    var media = layer.querySelector('.bg-media');

    function value(name) {
      if (name === 'fit') {
        var checked = form.querySelector('input[name="fit"]:checked');
        return checked ? checked.value : DEFAULTS.fit;
      }
      var input = form.querySelector('input[name="' + name + '"]');
      return input ? parseInt(input.value, 10) : DEFAULTS[name];
    }

    function apply() {
      var position = value('position_x') + '% ' + value('position_y') + '%';
      var fit = value('fit');
      layer.style.opacity = String(value('opacity') / 100);
      if (media.tagName === 'VIDEO') {
        media.style.objectFit = fit;
        media.style.objectPosition = position;
      } else {
        media.style.backgroundSize = fit;
        media.style.backgroundPosition = position;
      }
      media.style.transformOrigin = position;
      media.style.transform = value('zoom') !== 100 ? 'scale(' + value('zoom') / 100 + ')' : '';

      form.querySelectorAll('input[type="range"]').forEach(function (input) {
        var output = form.querySelector('output[for="' + input.id + '"]');
        if (output) {
          output.textContent = input.value + '%';
        }
      });
    }

    form.addEventListener('input', apply);
    form.addEventListener('change', apply);

    var reset = form.querySelector('[data-bg-reset]');
    if (reset) {
      reset.addEventListener('click', function () {
        Object.keys(DEFAULTS).forEach(function (name) {
          if (name === 'fit') {
            var radio = form.querySelector('input[name="fit"][value="' + DEFAULTS.fit + '"]');
            if (radio) {
              radio.checked = true;
            }
          } else {
            var input = form.querySelector('input[name="' + name + '"]');
            if (input) {
              input.value = DEFAULTS[name];
            }
          }
        });
        apply();
      });
    }
  });
})();
