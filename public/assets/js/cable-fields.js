// Leitungs-Abschnitt im Artikelformular (Migration 125):
// - nur sichtbar, wenn die Kategorie "Leitungen" gewählt ist,
// - bietet je Leitungstyp nur die erlaubten Steckverbinder an,
// - übernimmt die Vorgabe des Leitungstyps zur Elektroprüfung/Schutzklasse,
// - schlägt den Produktnamen aus Typ, Länge und Steckverbindern vor.
(function () {
  'use strict';

  var section = document.getElementById('cable_section');
  if (!section) {
    return;
  }

  var config = {};
  try {
    config = JSON.parse(section.getAttribute('data-cable-config') || '{}');
  } catch (e) { /* leer lassen */ }

  var cableCategory = section.getAttribute('data-cable-category');
  var isNewArticle = section.getAttribute('data-new-article') === '1';
  var category = document.getElementById('category_id');
  var type = document.getElementById('cable_type_id');
  var length = document.getElementById('cable_length');
  var connectors = section.querySelectorAll('[data-cable-connector]');
  var name = document.getElementById('name');
  var inspection = document.getElementById('requires_electrical_inspection');
  var protectionClass = document.getElementById('protection_class');
  var lastSuggestion = null;
  var namePlaceholder = name ? name.getAttribute('placeholder') : null;

  function isCable() {
    return !!cableCategory && category && category.value === cableCategory;
  }

  function filterConnectors() {
    var allowed = type.value && config[type.value] ? config[type.value].connectors : null;
    connectors.forEach(function (select) {
      Array.prototype.forEach.call(select.options, function (option) {
        if (!option.value) {
          return;
        }
        var ok = allowed === null || allowed.indexOf(option.value) !== -1;
        option.hidden = !ok;
        option.disabled = !ok;
        if (!ok && option.selected) {
          select.value = '';
        }
      });
      // Gibt es genau eine passende Option, direkt auswählen.
      if (allowed !== null && !select.value) {
        var visible = Array.prototype.filter.call(select.options, function (o) { return o.value && !o.disabled; });
        if (visible.length === 1) {
          select.value = visible[0].value;
        }
      }
    });
  }

  function applyInspectionDefault() {
    var entry = type.value ? config[type.value] : null;
    if (!entry || !inspection || inspection.disabled) {
      return;
    }
    inspection.checked = !!entry.inspection;
    if (entry.inspection && entry.protectionClass && protectionClass) {
      protectionClass.value = entry.protectionClass;
    }
    inspection.dispatchEvent(new Event('change'));
  }

  function suggestion() {
    var entry = type.value ? config[type.value] : null;
    var a = connectors[0] && connectors[0].selectedIndex > 0 ? connectors[0].options[connectors[0].selectedIndex].text : '';
    var b = connectors[1] && connectors[1].selectedIndex > 0 ? connectors[1].options[connectors[1].selectedIndex].text : '';
    var len = (length.value || '').trim().replace('.', ',');
    if (!entry || !len) {
      return null;
    }
    var ends = [a, b].filter(function (x) { return x; }).join(' / ');
    return (entry.name + ' ' + len + ' m ' + ends).replace(/\s+/g, ' ').trim();
  }

  function updateName() {
    if (!name || !isCable()) {
      return;
    }
    var proposal = suggestion();
    if (proposal && (name.value.trim() === '' || name.value === lastSuggestion)) {
      name.value = proposal;
      lastSuggestion = proposal;
    }
  }

  function syncVisibility() {
    var active = isCable();
    section.classList.toggle('hidden', !active);
    if (name) {
      name.required = !active;
      name.setAttribute('placeholder', active ? 'wird aus Leitungstyp, Länge und Steckverbindern gebildet' : (namePlaceholder || ''));
    }
  }

  type.addEventListener('change', function () {
    filterConnectors();
    applyInspectionDefault();
    updateName();
  });
  length.addEventListener('input', updateName);
  connectors.forEach(function (select) { select.addEventListener('change', updateName); });
  if (category) {
    category.addEventListener('change', function () {
      syncVisibility();
      if (isCable()) {
        filterConnectors();
        if (isNewArticle) {
          applyInspectionDefault();
        }
        updateName();
      }
    });
  }

  syncVisibility();
  filterConnectors();
})();
