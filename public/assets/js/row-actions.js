// Zeilen-Aktionen als Kontextmenü (⋮) auf schmalen Bildschirmen.
//
// Listen zeigen ihre Aktionen (Bearbeiten, Löschen und ggf. weitere wie
// „RMA“) als kleine Links/Buttons nebeneinander. Unter 640 px Breite
// werden diese Gruppen per CSS ausgeblendet und durch einen ⋮-Button
// ersetzt, der dieselben Aktionen als Menü öffnet. Die Original-Elemente
// bleiben im DOM: Links werden angesprungen, Formulare über ihren eigenen
// Absenden-Button ausgelöst (die globale data-confirm-Rückfrage greift
// dadurch unverändert).
//
// Erkennung ohne Änderungen an den einzelnen Views: Ausgangspunkt sind
// Lösch-Formulare (action endet auf /loeschen) und Bearbeiten-Links/
// -Buttons. Deren Eltern-Element gilt als Aktionsgruppe, wenn es nur aus
// Aktionen besteht (Links, Buttons, Formulare mit nur versteckten Feldern),
// mindestens zwei enthält und die Aktionen klein dargestellt werden (Zeilen-
// Aktionen, text-xs) — große Kopf-Buttons von Detailseiten bleiben, wie sie
// sind. Eigene Gruppen lassen sich mit data-row-actions erzwingen bzw. mit
// data-row-actions="off" ausschließen.
(function () {
  'use strict';

  var BREAKPOINT = 640;
  var EDIT_TEXT = /^(bearbeiten|edit|ändern)$/i;
  var SEPARATOR_TEXT = /^[\s|·•–-]*$/;
  var EXCLUDE = 'details, [data-dropdown-menu], dialog, nav, header, [data-row-actions="off"], .ra-menu';

  var style = document.createElement('style');
  style.textContent =
    '.ra-toggle{display:none}' +
    '@media (max-width:' + (BREAKPOINT - 0.02) + 'px){' +
    '.ra-group>:not(.ra-toggle){display:none!important}' +
    '.ra-toggle{display:inline-flex}' +
    '}' +
    '.ra-toggle{align-items:center;justify-content:center;width:2.25rem;height:2.25rem;margin:-.375rem -.5rem -.375rem 0;border:0;border-radius:9999px;background:transparent;color:#64748b;cursor:pointer;flex:none}' +
    '.ra-toggle:hover,.ra-toggle[aria-expanded="true"]{background:#f1f5f9;color:#0f172a}' +
    '.ra-toggle:focus-visible{outline:2px solid rgb(var(--color-brand-500,59 130 246));outline-offset:1px}' +
    'html.dark .ra-toggle{color:#94a3b8}' +
    'html.dark .ra-toggle:hover,html.dark .ra-toggle[aria-expanded="true"]{background:#1e293b;color:#f1f5f9}' +
    '.ra-menu{position:fixed;z-index:70;min-width:11rem;max-width:calc(100vw - 16px);padding:.25rem 0;border:1px solid #e2e8f0;border-radius:.5rem;background:#fff;box-shadow:0 10px 25px -5px rgba(15,23,42,.18),0 4px 8px -4px rgba(15,23,42,.1)}' +
    '.ra-menu[hidden]{display:none}' +
    '.ra-menu .ra-item{display:block;width:100%;padding:.625rem 1rem;border:0;background:none;text-align:left;font:inherit;font-size:.875rem;line-height:1.25rem;color:#334155;text-decoration:none;cursor:pointer;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
    '.ra-menu .ra-item:hover,.ra-menu .ra-item:focus{background:#f8fafc;outline:none}' +
    '.ra-menu .ra-item.ra-danger{color:#dc2626}' +
    '.ra-menu .ra-sep{height:1px;margin:.25rem 0;background:#f1f5f9}' +
    'html.dark .ra-menu{border-color:#334155;background:#0f172a}' +
    'html.dark .ra-menu .ra-item{color:#cbd5e1}' +
    'html.dark .ra-menu .ra-item:hover,html.dark .ra-menu .ra-item:focus{background:#1e293b}' +
    'html.dark .ra-menu .ra-item.ra-danger{color:#f87171}' +
    'html.dark .ra-menu .ra-sep{background:#1e293b}';
  document.head.appendChild(style);

  var DOTS = '<svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><circle cx="10" cy="4" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="10" cy="16" r="1.6"/></svg>';

  // ---- Erkennung -----------------------------------------------------

  /** Ist das Formular eine reine Aktion (nur versteckte Felder + ein Button)? */
  function isActionForm(form) {
    var fields = form.querySelectorAll('input, select, textarea, button');
    var buttons = 0;
    for (var i = 0; i < fields.length; i++) {
      var f = fields[i];
      if (f.tagName === 'INPUT' && f.type === 'hidden') {
        continue;
      }
      if ((f.tagName === 'BUTTON' && f.type !== 'button') || (f.tagName === 'INPUT' && f.type === 'submit')) {
        buttons++;
        continue;
      }
      return false;
    }
    return buttons === 1;
  }

  /** Aktion, die ein Element darstellt (A, BUTTON, Aktions-FORM), oder null. */
  function actionOf(el) {
    // Bild-Kacheln (Foto-Link mit Lösch-Overlay) und überlagerte Elemente sind keine Zeilen-Aktionen.
    if (el.querySelector('img, picture, video, canvas') || /\babsolute\b/.test(el.className || '')) {
      return null;
    }
    if (el.tagName === 'A' && el.getAttribute('href')) {
      return el;
    }
    if (el.tagName === 'BUTTON') {
      return el;
    }
    if (el.tagName === 'FORM') {
      return isActionForm(el) ? el : null;
    }
    // Hülle (span/div) um genau eine Aktion, z. B. <span><form>…</form></span>
    var children = el.children;
    if (children.length === 1 && textOnlySeparators(el)) {
      return actionOf(children[0]);
    }
    return null;
  }

  function textOnlySeparators(el) {
    for (var n = el.firstChild; n; n = n.nextSibling) {
      if (n.nodeType === 3 && !SEPARATOR_TEXT.test(n.nodeValue)) {
        return false;
      }
    }
    return true;
  }

  /** Liste der Aktionen, wenn $group nur aus Aktionen besteht, sonst null. */
  function actionsOf(group) {
    if (!textOnlySeparators(group)) {
      return null;
    }
    var actions = [];
    for (var i = 0; i < group.children.length; i++) {
      var child = group.children[i];
      if (child.tagName === 'SCRIPT' || child.tagName === 'TEMPLATE' || child.classList.contains('ra-toggle')) {
        continue;
      }
      if (child.hidden || child.classList.contains('hidden')) {
        continue; // ausgeblendete Aktionen
      }
      var action = actionOf(child);
      if (!action) {
        return null;
      }
      actions.push(action);
    }
    return actions;
  }

  function isSmall(action) {
    var target = action.tagName === 'FORM' ? action.querySelector('button, input[type="submit"]') : action;
    return target && parseFloat(getComputedStyle(target).fontSize) < 14;
  }

  function seedElements(root) {
    var seeds = [];
    root.querySelectorAll('form[action$="/loeschen"], a[href*="/bearbeiten"], a[href*="edit_"], button, a').forEach(function (el) {
      if (el.tagName === 'FORM' || /\/bearbeiten|edit_/.test(el.getAttribute('href') || '')) {
        seeds.push(el);
      } else if (EDIT_TEXT.test((el.textContent || '').trim())) {
        seeds.push(el);
      }
    });
    return seeds;
  }

  function scan(root) {
    var groups = [];
    seedElements(root || document).forEach(function (seed) {
      if (seed.closest(EXCLUDE) || seed.closest('.ra-group')) {
        return;
      }
      // Vom Ausgangspunkt nach oben, bis ein Element mehrere Aktionen enthält.
      var item = seed.tagName === 'BUTTON' && seed.form && seed.closest('form') === seed.form ? seed.form : seed;
      var group = item.parentElement;
      for (var depth = 0; depth < 3 && group && group !== document.body; depth++) {
        if (group.tagName === 'TR' || group.tagName === 'TBODY' || group.tagName === 'TABLE' || group.tagName === 'FORM') {
          return;
        }
        var actions = actionsOf(group);
        if (actions === null) {
          return;
        }
        if (actions.length >= 2) {
          if (groups.indexOf(group) === -1 && (group.hasAttribute('data-row-actions') || isSmall(actions[0]) || isSmall(actions[actions.length - 1]))) {
            groups.push(group);
          }
          return;
        }
        group = group.parentElement;
      }
    });
    document.querySelectorAll('[data-row-actions]:not([data-row-actions="off"]):not(.ra-group)').forEach(function (group) {
      if (groups.indexOf(group) === -1 && actionsOf(group)) {
        groups.push(group);
      }
    });
    groups.forEach(enhance);
  }

  function enhance(group) {
    group.classList.add('ra-group');
    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'ra-toggle';
    toggle.setAttribute('aria-haspopup', 'menu');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Aktionen');
    toggle.title = 'Aktionen';
    toggle.innerHTML = DOTS;
    group.insertBefore(toggle, group.firstChild);
  }

  // ---- Menü ------------------------------------------------------------

  var menu = document.createElement('div');
  menu.className = 'ra-menu';
  menu.setAttribute('role', 'menu');
  menu.hidden = true;
  var openToggle = null;

  function labelOf(action) {
    var el = action.tagName === 'FORM' ? action.querySelector('button, input[type="submit"]') : action;
    var text = el.tagName === 'INPUT' ? el.value : (el.textContent || '');
    text = text.replace(/\s+/g, ' ').trim();
    return text || el.getAttribute('aria-label') || el.getAttribute('title') || 'Aktion';
  }

  function isDanger(action) {
    var el = action.tagName === 'FORM' ? action.querySelector('button, input[type="submit"]') : action;
    return (action.tagName === 'FORM' && (/\/loeschen$/.test(action.getAttribute('action') || '') || action.getAttribute('data-confirm-style') === 'danger'))
      || /\btext-red-/.test(el.className || '');
  }

  function buildMenu(group) {
    menu.innerHTML = '';
    var actions = actionsOf(group) || [];
    var dangerStarted = false;
    actions.forEach(function (action) {
      var danger = isDanger(action);
      if (danger && !dangerStarted && menu.children.length > 0) {
        var sep = document.createElement('div');
        sep.className = 'ra-sep';
        sep.setAttribute('role', 'separator');
        menu.appendChild(sep);
      }
      dangerStarted = dangerStarted || danger;
      var item;
      if (action.tagName === 'A') {
        item = document.createElement('a');
        item.href = action.href;
        ['target', 'rel', 'download'].forEach(function (attr) {
          if (action.hasAttribute(attr)) {
            item.setAttribute(attr, action.getAttribute(attr));
          }
        });
        // Links mit eigenem Klick-Verhalten (z. B. PDF-Viewer, Modals) über das Original auslösen.
        item.addEventListener('click', function (e) {
          if (action.hasAttribute('data-ra-direct')) {
            return;
          }
          e.preventDefault();
          close(false);
          action.click();
        });
      } else {
        item = document.createElement('button');
        item.type = 'button';
        item.addEventListener('click', function (e) {
          close(false);
          var target = action.tagName === 'FORM' ? action.querySelector('button:not([type="button"]), input[type="submit"]') : action;
          // Umschalttaste wie beim Original: Rückfrage überspringen.
          target.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, shiftKey: e.shiftKey }));
        });
      }
      item.className = 'ra-item' + (danger ? ' ra-danger' : '');
      item.setAttribute('role', 'menuitem');
      item.textContent = labelOf(action);
      menu.appendChild(item);
    });
  }

  function position(toggle) {
    var r = toggle.getBoundingClientRect();
    menu.style.left = '0px';
    menu.style.top = '0px';
    var w = menu.offsetWidth;
    var h = menu.offsetHeight;
    var left = Math.min(Math.max(8, r.right - w), window.innerWidth - w - 8);
    var top = r.bottom + 4;
    if (top + h > window.innerHeight - 8 && r.top - h - 4 >= 8) {
      top = r.top - h - 4;
    }
    menu.style.left = left + 'px';
    menu.style.top = Math.max(8, top) + 'px';
  }

  function open(toggle) {
    if (!menu.parentNode) {
      document.body.appendChild(menu);
    }
    buildMenu(toggle.parentElement);
    menu.hidden = false;
    position(toggle);
    toggle.setAttribute('aria-expanded', 'true');
    openToggle = toggle;
    var first = menu.querySelector('.ra-item');
    if (first) {
      first.focus({ preventScroll: true });
    }
  }

  function close(restoreFocus) {
    if (!openToggle) {
      return;
    }
    var toggle = openToggle;
    openToggle = null;
    menu.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    if (restoreFocus) {
      toggle.focus({ preventScroll: true });
    }
  }

  document.addEventListener('click', function (e) {
    var toggle = e.target.closest && e.target.closest('.ra-toggle');
    if (toggle) {
      e.preventDefault();
      e.stopPropagation(); // Zeilen mit eigenem Klick-Ziel nicht auslösen
      if (openToggle === toggle) {
        close(false);
      } else {
        close(false);
        open(toggle);
      }
      return;
    }
    if (openToggle && !menu.contains(e.target)) {
      close(false);
    }
  }, true);

  document.addEventListener('keydown', function (e) {
    if (!openToggle) {
      return;
    }
    var items = Array.prototype.slice.call(menu.querySelectorAll('.ra-item'));
    var index = items.indexOf(document.activeElement);
    if (e.key === 'Escape') {
      e.preventDefault();
      close(true);
    } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      var next = e.key === 'ArrowDown' ? index + 1 : index - 1;
      items[(next + items.length) % items.length].focus();
    } else if (e.key === 'Tab') {
      close(false);
    }
  });

  window.addEventListener('resize', function () { close(false); });
  // Beim Scrollen mitwandern; erst schließen, wenn der ⋮-Button aus dem Bild ist.
  window.addEventListener('scroll', function (e) {
    if (!openToggle || menu.contains(e.target)) {
      return;
    }
    var r = openToggle.getBoundingClientRect();
    if (r.bottom < 0 || r.top > window.innerHeight || r.right < 0 || r.left > window.innerWidth) {
      close(false);
    } else {
      position(openToggle);
    }
  }, true);

  // ---- Start & nachgeladene Inhalte (Pagination per AJAX, Modals) --------

  var pending = null;
  function scheduleScan() {
    if (pending) {
      return;
    }
    pending = setTimeout(function () {
      pending = null;
      scan(document);
    }, 80);
  }

  function start() {
    scan(document);
    new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        var m = mutations[i];
        if (m.target === menu || menu.contains(m.target)) {
          continue;
        }
        for (var j = 0; j < m.addedNodes.length; j++) {
          var node = m.addedNodes[j];
          if (node.nodeType === 1 && !node.classList.contains('ra-toggle') && node !== menu) {
            scheduleScan();
            return;
          }
        }
      }
    }).observe(document.body, { childList: true, subtree: true });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
