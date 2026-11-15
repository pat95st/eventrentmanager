/*
 * Onboarding tour (migration 161).
 *
 * Reads its steps from window.ONBOARDING_TOUR (built per user in
 * resources/views/partials/onboarding_tour.php). Each step points at an
 * element marked with data-tour="..."; the page is dimmed, a spot lands on
 * that element and a drawn arrow runs from the note card to it.
 *
 * Skipping (button or Escape) and finishing both call completeUrl once, so
 * the tour does not start again on its own.
 */
(function () {
  'use strict';

  var cfg = window.ONBOARDING_TOUR;
  if (!cfg || !Array.isArray(cfg.steps) || cfg.steps.length === 0) {
    return;
  }

  var SVG_NS = 'http://www.w3.org/2000/svg';
  var NARROW = window.matchMedia('(max-width: 1023px)');
  var PHONE = window.matchMedia('(max-width: 639px)');
  var REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)');
  var SPOT_PAD = 6;
  var GUTTER = 16;
  var GAP = 84; // room between card and target for the arrow to curve

  var labels = cfg.labels || {};
  var steps = [];
  var index = 0;
  var root, spot, svg, line, head, card, progress, count, title, body, live;
  var btnSkip, btnBack, btnNext;
  var lastFocus = null;
  var finished = false;
  var rafId = 0;

  function ensureStylesheet() {
    if (document.querySelector('link[data-onboarding-tour]')) {
      return;
    }
    var script = document.querySelector('script[src*="onboarding-tour.js"]');
    var version = script ? (script.getAttribute('src').split('?')[1] || '') : '';
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/assets/css/onboarding-tour.css' + (version ? '?' + version : '');
    link.setAttribute('data-onboarding-tour', '');
    document.head.appendChild(link);
    return link;
  }

  function isVisible(el) {
    if (!el) {
      return false;
    }
    var r = el.getBoundingClientRect();
    if (r.width === 0 && r.height === 0) {
      return false;
    }
    var style = window.getComputedStyle(el);
    return style.visibility !== 'hidden' && style.display !== 'none';
  }

  function findTarget(selector) {
    if (!selector) {
      return null;
    }
    var nodes = document.querySelectorAll(selector);
    for (var i = 0; i < nodes.length; i++) {
      if (isVisible(nodes[i])) {
        return nodes[i];
      }
    }
    return null;
  }

  /* Adapts the configured steps to the current screen width. */
  function buildSteps() {
    var narrow = NARROW.matches;
    var result = [];
    var navItems = [];
    var menuIndex = -1;

    cfg.steps.forEach(function (step) {
      if (narrow && step.desktopOnly) {
        return;
      }
      if (narrow && step.group === 'nav') {
        navItems.push(step.title);
        if (menuIndex === -1) {
          menuIndex = result.length;
          result.push(null); // placeholder for the folded menu step
        }
        return;
      }
      var s = {
        id: step.id,
        title: step.title,
        body: narrow && step.mobileBody ? step.mobileBody : step.body,
        target: narrow && step.mobileTarget ? step.mobileTarget : step.target,
        optional: !!step.optional,
        list: null
      };
      result.push(s);
    });

    if (menuIndex !== -1) {
      result[menuIndex] = {
        id: 'menu',
        title: cfg.menu.title,
        body: cfg.menu.body,
        target: cfg.menu.target,
        optional: false,
        list: navItems
      };
    }

    // Optional steps only make sense where their element exists (e.g. the
    // dashboard step on the home page).
    return result.filter(function (s) {
      return !s.optional || findTarget(s.target) !== null;
    });
  }

  function el(tag, className, parent) {
    var node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (parent) {
      parent.appendChild(node);
    }
    return node;
  }

  function buildDom() {
    root = el('div', 'ot-root ot-entering');
    root.setAttribute('data-onboarding-tour-root', '');
    el('div', 'ot-blocker', root);
    spot = el('div', 'ot-spot ot-spot-off', root);

    svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('class', 'ot-arrow');
    svg.setAttribute('aria-hidden', 'true');
    line = document.createElementNS(SVG_NS, 'path');
    line.setAttribute('class', 'ot-arrow-line');
    head = document.createElementNS(SVG_NS, 'path');
    head.setAttribute('class', 'ot-arrow-head');
    svg.appendChild(line);
    svg.appendChild(head);
    root.appendChild(svg);

    card = el('div', 'ot-card', root);
    card.setAttribute('role', 'dialog');
    card.setAttribute('aria-modal', 'true');
    card.setAttribute('aria-label', labels.dialog || '');
    card.setAttribute('aria-describedby', 'ot-body');

    progress = el('div', 'ot-progress', card);
    progress.setAttribute('aria-hidden', 'true');
    count = el('p', 'ot-count', card);
    title = el('h2', 'ot-title', card);
    title.id = 'ot-title';
    title.tabIndex = -1;
    body = el('div', 'ot-body', card);
    body.id = 'ot-body';

    var actions = el('div', 'ot-actions', card);
    btnSkip = el('button', 'ot-link', actions);
    btnSkip.type = 'button';
    btnSkip.textContent = labels.skip || 'Skip';
    el('span', 'ot-spacer', actions);
    btnBack = el('button', 'ot-btn ot-btn-ghost', actions);
    btnBack.type = 'button';
    btnBack.textContent = labels.back || 'Back';
    btnNext = el('button', 'ot-btn ot-btn-primary', actions);
    btnNext.type = 'button';

    live = el('div', 'ot-sr', root);
    live.setAttribute('aria-live', 'polite');

    btnSkip.addEventListener('click', function () { close(); });
    btnBack.addEventListener('click', function () { go(index - 1); });
    btnNext.addEventListener('click', function () {
      if (index >= steps.length - 1) {
        close();
      } else {
        go(index + 1);
      }
    });

    document.body.appendChild(root);
  }

  function renderText(step) {
    var first = index === 0;
    var last = index === steps.length - 1;
    var center = !step.el;

    card.classList.toggle('ot-card-center', center);
    card.setAttribute('aria-labelledby', 'ot-title');

    progress.textContent = '';
    for (var i = 0; i < steps.length; i++) {
      var seg = el('span', i <= index ? 'ot-done' : '', progress);
      seg.setAttribute('data-step', String(i));
    }

    var stepOf = (labels.stepOf || '{current} / {total}')
      .replace('{current}', String(index + 1))
      .replace('{total}', String(steps.length));
    count.textContent = stepOf;
    count.hidden = first;
    title.textContent = step.title;

    body.textContent = '';
    el('p', '', body).textContent = step.body;
    if (step.list && step.list.length) {
      var ul = el('ul', '', body);
      step.list.forEach(function (item) {
        el('li', '', ul).textContent = item;
      });
    }

    btnBack.hidden = first;
    btnSkip.hidden = last;
    btnNext.textContent = first ? (labels.start || labels.next) : (last ? labels.finish : labels.next);
    live.textContent = stepOf + ': ' + step.title;
  }

  function clamp(v, min, max) {
    return Math.max(min, Math.min(max, v));
  }

  /* Places the card next to the target where there is the most room. */
  function placeCard(rect) {
    var vw = window.innerWidth;
    var vh = window.innerHeight;
    var cw = card.offsetWidth;
    var ch = card.offsetHeight;

    if (!rect) {
      card.style.left = Math.round((vw - cw) / 2) + 'px';
      card.style.top = Math.round(Math.max(GUTTER, (vh - ch) / 2)) + 'px';
      return null;
    }

    if (PHONE.matches) {
      // Sheet at the edge away from the target.
      var targetInTopHalf = rect.top + rect.height / 2 < vh / 2;
      var topPos = targetInTopHalf ? vh - ch - GUTTER : GUTTER;
      card.style.left = GUTTER + 'px';
      card.style.top = Math.round(topPos) + 'px';
      return targetInTopHalf ? 'below' : 'above';
    }

    var cx = rect.left + rect.width / 2;
    var cy = rect.top + rect.height / 2;
    // Nudge the card towards the middle of the screen so the arrow bends.
    var nudge = cx < vw / 2 ? 48 : -48;
    var options = [
      { side: 'below', fits: rect.bottom + GAP + ch <= vh - GUTTER,
        left: cx - cw / 2 + nudge, top: rect.bottom + GAP },
      { side: 'above', fits: rect.top - GAP - ch >= GUTTER,
        left: cx - cw / 2 + nudge, top: rect.top - GAP - ch },
      { side: 'right', fits: rect.right + GAP + cw <= vw - GUTTER,
        left: rect.right + GAP, top: cy - ch / 2 + 32 },
      { side: 'left', fits: rect.left - GAP - cw >= GUTTER,
        left: rect.left - GAP - cw, top: cy - ch / 2 + 32 }
    ];
    var choice = options[0];
    for (var i = 0; i < options.length; i++) {
      if (options[i].fits) {
        choice = options[i];
        break;
      }
    }
    card.style.left = Math.round(clamp(choice.left, GUTTER, vw - cw - GUTTER)) + 'px';
    card.style.top = Math.round(clamp(choice.top, GUTTER, vh - ch - GUTTER)) + 'px';
    return choice.side;
  }

  function cardRect() {
    // Use the target position (style) rather than the animated one.
    var left = parseFloat(card.style.left) || 0;
    var top = parseFloat(card.style.top) || 0;
    if (PHONE.matches) {
      left = GUTTER;
    }
    return { left: left, top: top, right: left + card.offsetWidth, bottom: top + card.offsetHeight,
      width: card.offsetWidth, height: card.offsetHeight };
  }

  /* Draws a curved arrow from the card edge to the spot around the target. */
  function drawArrow(rect, side, animate) {
    if (!rect || !side) {
      line.setAttribute('d', '');
      head.setAttribute('d', '');
      svg.classList.remove('ot-drawn');
      return;
    }
    var c = cardRect();
    var pad = SPOT_PAD + 6;
    var tx = rect.left + rect.width / 2;
    var ty = rect.top + rect.height / 2;
    var sx, sy, ex, ey;

    if (side === 'below' || side === 'above') {
      sx = clamp(tx, c.left + 36, c.right - 36);
      // Start off-centre so the line never runs dead straight.
      if (Math.abs(sx - tx) < 24) {
        sx = clamp(tx + (tx < window.innerWidth / 2 ? 40 : -40), c.left + 28, c.right - 28);
      }
      sy = side === 'below' ? c.top - 8 : c.bottom + 8;
      ex = clamp(sx + (tx - sx) * 0.35, rect.left + 4, rect.right - 4);
      if (rect.width < 80) {
        ex = tx;
      }
      ey = side === 'below' ? rect.bottom + pad : rect.top - pad;
    } else {
      sx = side === 'right' ? c.left - 8 : c.right + 8;
      sy = clamp(ty + 28, c.top + 24, c.bottom - 24);
      ex = side === 'right' ? rect.right + pad : rect.left - pad;
      ey = ty;
    }

    var dx = ex - sx;
    var dy = ey - sy;
    var dist = Math.sqrt(dx * dx + dy * dy) || 1;
    // Control point: perpendicular to the line, bending towards the screen centre.
    var mx = (sx + ex) / 2;
    var my = (sy + ey) / 2;
    var nx = -dy / dist;
    var ny = dx / dist;
    var towardsCentre = ((window.innerWidth / 2 - mx) * nx + (window.innerHeight / 2 - my) * ny) >= 0 ? 1 : -1;
    var bend = Math.min(70, dist * 0.28) * towardsCentre;
    var qx = mx + nx * bend;
    var qy = my + ny * bend;

    line.setAttribute('d', 'M' + sx.toFixed(1) + ' ' + sy.toFixed(1) +
      ' Q' + qx.toFixed(1) + ' ' + qy.toFixed(1) + ' ' + ex.toFixed(1) + ' ' + ey.toFixed(1));

    // Open chevron head along the tangent at the end point.
    var ang = Math.atan2(ey - qy, ex - qx);
    var len = 12;
    var spread = 0.5;
    var h1x = ex - len * Math.cos(ang - spread);
    var h1y = ey - len * Math.sin(ang - spread);
    var h2x = ex - len * Math.cos(ang + spread);
    var h2y = ey - len * Math.sin(ang + spread);
    head.setAttribute('d', 'M' + h1x.toFixed(1) + ' ' + h1y.toFixed(1) + ' L' + ex.toFixed(1) + ' ' +
      ey.toFixed(1) + ' L' + h2x.toFixed(1) + ' ' + h2y.toFixed(1));

    var total = line.getTotalLength ? line.getTotalLength() : dist;
    line.style.strokeDasharray = String(total);
    if (animate && !REDUCED.matches) {
      svg.classList.remove('ot-drawn');
      line.style.transition = 'none';
      line.style.strokeDashoffset = String(total);
      // Force a reflow, then let the CSS transition draw the line.
      void line.getBoundingClientRect();
      line.style.transition = '';
      line.style.strokeDashoffset = '0';
      svg.classList.add('ot-drawn');
    } else {
      line.style.transition = 'none';
      line.style.strokeDashoffset = '0';
      svg.classList.add('ot-drawn');
    }
  }

  function layout(animate) {
    var step = steps[index];
    if (!step) {
      return;
    }
    step.el = findTarget(step.target);
    var rect = step.el ? step.el.getBoundingClientRect() : null;

    if (rect) {
      spot.classList.remove('ot-spot-off');
      var radius = parseFloat(window.getComputedStyle(step.el).borderRadius) || 6;
      spot.style.left = (rect.left - SPOT_PAD) + 'px';
      spot.style.top = (rect.top - SPOT_PAD) + 'px';
      spot.style.width = (rect.width + SPOT_PAD * 2) + 'px';
      spot.style.height = (rect.height + SPOT_PAD * 2) + 'px';
      spot.style.borderRadius = (radius + SPOT_PAD) + 'px';
    } else {
      spot.classList.add('ot-spot-off');
      spot.style.left = (window.innerWidth / 2) + 'px';
      spot.style.top = (window.innerHeight / 2) + 'px';
      spot.style.width = '0px';
      spot.style.height = '0px';
    }

    var side = placeCard(rect);
    drawArrow(rect, side, animate);
  }

  function go(next) {
    if (next < 0 || next >= steps.length) {
      return;
    }
    index = next;
    var step = steps[index];
    step.el = findTarget(step.target);
    if (step.el) {
      var r = step.el.getBoundingClientRect();
      if (r.top < 0 || r.bottom > window.innerHeight) {
        step.el.scrollIntoView({ block: 'center', behavior: REDUCED.matches ? 'auto' : 'smooth' });
      }
    }
    renderText(step);
    layout(true);
    btnNext.focus({ preventScroll: true });
  }

  function scheduleLayout() {
    if (rafId) {
      return;
    }
    rafId = window.requestAnimationFrame(function () {
      rafId = 0;
      layout(false);
    });
  }

  function onBreakpointChange() {
    var currentId = steps[index] ? steps[index].id : null;
    steps = buildSteps();
    var found = 0;
    for (var i = 0; i < steps.length; i++) {
      if (steps[i].id === currentId) {
        found = i;
        break;
      }
    }
    // A desktop nav step folds into the "menu" step on narrow screens.
    if (currentId && currentId.indexOf('nav-') === 0) {
      for (var j = 0; j < steps.length; j++) {
        if (steps[j].id === 'menu') {
          found = j;
          break;
        }
      }
    }
    go(found);
  }

  function onKeydown(e) {
    if (e.key === 'Escape') {
      e.preventDefault();
      close();
      return;
    }
    if (e.key === 'ArrowRight' && index < steps.length - 1) {
      e.preventDefault();
      go(index + 1);
      return;
    }
    if (e.key === 'ArrowLeft' && index > 0) {
      e.preventDefault();
      go(index - 1);
      return;
    }
    if (e.key === 'Tab') {
      // Keep keyboard focus inside the card.
      var focusable = Array.prototype.filter.call(card.querySelectorAll('button'), function (b) {
        return !b.hidden;
      });
      if (focusable.length === 0) {
        return;
      }
      var firstEl = focusable[0];
      var lastEl = focusable[focusable.length - 1];
      if (e.shiftKey && (document.activeElement === firstEl || !card.contains(document.activeElement))) {
        e.preventDefault();
        lastEl.focus();
      } else if (!e.shiftKey && (document.activeElement === lastEl || !card.contains(document.activeElement))) {
        e.preventDefault();
        firstEl.focus();
      }
    }
  }

  function record() {
    if (!cfg.record || !cfg.completeUrl) {
      return;
    }
    var data = new URLSearchParams();
    data.append('csrf_token', window.CSRF_TOKEN || '');
    try {
      fetch(cfg.completeUrl, {
        method: 'POST',
        credentials: 'same-origin',
        keepalive: true,
        headers: {
          'X-CSRF-Token': window.CSRF_TOKEN || '',
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: data.toString()
      }).catch(function () {});
    } catch (err) {
      // Not recorded: the tour simply shows up again next time.
    }
  }

  function close() {
    if (finished) {
      return;
    }
    finished = true;
    record();
    document.removeEventListener('keydown', onKeydown, true);
    window.removeEventListener('resize', scheduleLayout);
    window.removeEventListener('scroll', scheduleLayout, true);
    NARROW.removeEventListener && NARROW.removeEventListener('change', onBreakpointChange);
    PHONE.removeEventListener && PHONE.removeEventListener('change', scheduleLayout);

    var remove = function () {
      if (root && root.parentNode) {
        root.parentNode.removeChild(root);
      }
      if (lastFocus && typeof lastFocus.focus === 'function' && document.contains(lastFocus)) {
        lastFocus.focus({ preventScroll: true });
      }
    };
    if (REDUCED.matches) {
      remove();
    } else {
      root.classList.add('ot-leaving');
      window.setTimeout(remove, 200);
    }
  }

  function stripQueryFlag() {
    try {
      var url = new URL(window.location.href);
      if (url.searchParams.get('tour') !== null) {
        url.searchParams.delete('tour');
        window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash);
      }
    } catch (err) {}
  }

  function start() {
    stripQueryFlag();
    steps = buildSteps();
    if (steps.length === 0) {
      return;
    }
    lastFocus = document.activeElement;
    buildDom();
    renderText(steps[0]);
    layout(false);

    document.addEventListener('keydown', onKeydown, true);
    window.addEventListener('resize', scheduleLayout);
    window.addEventListener('scroll', scheduleLayout, true);
    if (NARROW.addEventListener) {
      NARROW.addEventListener('change', onBreakpointChange);
      PHONE.addEventListener('change', scheduleLayout);
    }

    window.requestAnimationFrame(function () {
      root.classList.remove('ot-entering');
      btnNext.focus({ preventScroll: true });
    });
  }

  function whenStyled(fn) {
    var link = ensureStylesheet();
    if (!link || link.sheet) {
      fn();
      return;
    }
    var done = false;
    var once = function () {
      if (!done) {
        done = true;
        fn();
      }
    };
    link.addEventListener('load', once);
    link.addEventListener('error', once);
    window.setTimeout(once, 1500);
  }

  // Let the page settle (fonts, dashboard tiles) before measuring.
  function boot() {
    whenStyled(function () {
      window.setTimeout(start, 250);
    });
  }

  if (document.readyState === 'complete') {
    boot();
  } else {
    window.addEventListener('load', boot);
  }

  // Lets other scripts (or the console) restart the tour on the same page.
  window.startOnboardingTour = function () {
    if (root && root.parentNode) {
      return;
    }
    finished = false;
    index = 0;
    cfg.record = false;
    start();
  };
})();
