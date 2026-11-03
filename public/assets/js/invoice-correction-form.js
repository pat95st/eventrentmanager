// Formular „Rechnungskorrektur“ (Teilkorrektur, Migration 121): Positionen
// hinzufügen/entfernen, Position aus der Rechnung als Gutschrift (−) oder
// Nachberechnung (+) übernehmen, Summen live berechnen. Die verbindliche
// Berechnung macht der Server (InvoiceCorrection::netTotal() usw.).
(function () {
  'use strict';

  var form = document.getElementById('invoice-correction-form');
  var body = document.getElementById('correction-lines');
  var template = document.getElementById('correction-line-template');
  if (!form || !body || !template) {
    return;
  }

  var vatRate = parseFloat(form.getAttribute('data-vat-rate')) || 0;
  var smallBusiness = form.getAttribute('data-small-business') === '1';
  var grossBefore = parseFloat(form.getAttribute('data-gross-before')) || 0;
  var nextIndex = parseInt(body.getAttribute('data-next-index'), 10) || 0;

  function parse(value) {
    var v = String(value || '').replace(/\s|€/g, '');
    if (v.indexOf(',') !== -1) {
      v = v.replace(/\./g, '').replace(',', '.');
    }
    var n = parseFloat(v);
    return isNaN(n) ? null : n;
  }

  function money(n) {
    return n.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
  }

  function round2(n) {
    return Math.round(n * 100) / 100;
  }

  function recalc() {
    var net = 0;
    body.querySelectorAll('[data-line]').forEach(function (row) {
      var qty = parse(row.querySelector('[data-qty]').value);
      var price = parse(row.querySelector('[data-price]').value);
      var cell = row.querySelector('[data-sum]');
      if (qty === null || price === null) {
        cell.textContent = '–';
        cell.style.color = '';
        return;
      }
      var sum = round2(qty * price);
      net += sum;
      cell.textContent = money(sum);
      cell.style.color = sum < 0 ? '#dc2626' : '';
    });
    net = round2(net);
    var tax = smallBusiness ? 0 : round2(net * vatRate / 100);
    var gross = round2(net + tax);
    var set = function (key, value) {
      var el = form.querySelector('[data-total="' + key + '"]');
      if (el) {
        el.textContent = money(value);
      }
    };
    set('net', net);
    set('tax', tax);
    set('gross', gross);
    set('after', round2(grossBefore + gross));
  }

  function addLine(description, quantity, price) {
    var fragment = template.content.cloneNode(true);
    var row = fragment.querySelector('[data-line]');
    var index = nextIndex++;
    row.querySelectorAll('[data-name]').forEach(function (input) {
      input.name = 'lines[' + index + '][' + input.getAttribute('data-name') + ']';
    });
    if (description !== undefined) {
      row.querySelector('[data-name="description"]').value = description;
      row.querySelector('[data-name="quantity"]').value = quantity;
      row.querySelector('[data-name="unit_price"]').value = price;
    }
    body.appendChild(fragment);
    recalc();
    return row;
  }

  document.getElementById('add-correction-line').addEventListener('click', function () {
    var row = addLine();
    row.querySelector('input').focus();
  });

  form.querySelectorAll('[data-take-line]').forEach(function (button) {
    button.addEventListener('click', function () {
      var select = document.getElementById('source_line');
      var option = select && select.options[select.selectedIndex];
      if (!option) {
        return;
      }
      var price = option.getAttribute('data-price');
      if (button.getAttribute('data-take-line') === 'credit') {
        price = price.charAt(0) === '-' ? price.slice(1) : '-' + price;
      }
      addLine(option.getAttribute('data-description'), option.getAttribute('data-quantity'), price);
    });
  });

  body.addEventListener('click', function (event) {
    var button = event.target.closest('[data-remove-line]');
    if (button) {
      button.closest('[data-line]').remove();
      recalc();
    }
  });
  body.addEventListener('input', recalc);

  if (!body.querySelector('[data-line]')) {
    addLine();
  }
  recalc();
})();
