// IP addresses in the inventory number form (migration 155):
// - shows the IPv4/IPv6 fields matching the selected network,
// - fills in the next free address when the network changes, as long as the
//   field still holds a suggestion (manual input is never overwritten),
// - never suggests an address twice within the same form.
(function () {
  'use strict';

  document.querySelectorAll('.js-network-fields').forEach(function (container) {
    var networks = {};
    try {
      networks = JSON.parse(container.getAttribute('data-networks') || '{}');
    } catch (e) { /* keep empty */ }

    var rows = Array.prototype.slice.call(container.querySelectorAll('.js-network-row'));

    function usedAddresses(networkId, field, exceptInput) {
      var used = [];
      rows.forEach(function (row) {
        var select = row.querySelector('.js-network-select');
        var input = row.querySelector(field);
        if (input !== exceptInput && select.value === networkId && input.value.trim() !== '') {
          used.push(input.value.trim().toLowerCase());
        }
      });
      return used;
    }

    function suggest(networkId, field, input) {
      var network = networks[networkId];
      if (!network) {
        return '';
      }
      var candidates = field === '.js-network-ip4' ? network.candidates4 : network.candidates6;
      var used = usedAddresses(networkId, field, input);
      for (var i = 0; i < candidates.length; i++) {
        if (used.indexOf(candidates[i].toLowerCase()) === -1) {
          return candidates[i];
        }
      }
      return '';
    }

    rows.forEach(function (row) {
      var select = row.querySelector('.js-network-select');
      var ip4 = row.querySelector('.js-network-ip4');
      var ip6 = row.querySelector('.js-network-ip6');
      var v4Box = row.querySelector('.js-network-v4');
      var v6Box = row.querySelector('.js-network-v6');
      var autoFill = row.getAttribute('data-suggested') === '1';

      [ip4, ip6].forEach(function (input) {
        input.addEventListener('input', function () { autoFill = false; });
      });

      select.addEventListener('change', function () {
        var network = networks[select.value];
        v4Box.classList.toggle('hidden', !!network && !network.v4);
        v6Box.classList.toggle('hidden', !network || !network.v6);
        ip4.placeholder = network && network.cidr4 ? network.cidr4 : 'z. B. 192.168.10.20';
        ip6.placeholder = network && network.cidr6 ? network.cidr6 : 'z. B. fd00:10::20';
        if (!network) {
          if (autoFill) { ip4.value = ''; ip6.value = ''; }
          return;
        }
        if (!network.v4) { ip4.value = ''; }
        if (!network.v6) { ip6.value = ''; }
        if (autoFill || (ip4.value === '' && ip6.value === '')) {
          ip4.value = '';
          ip6.value = '';
          if (network.v4) { ip4.value = suggest(select.value, '.js-network-ip4', ip4); }
          if (network.v6) { ip6.value = suggest(select.value, '.js-network-ip6', ip6); }
          autoFill = true;
        }
      });
    });
  });
})();
