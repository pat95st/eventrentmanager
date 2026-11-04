// Kontoführung (Migration 131): Liniendiagramm des Kontosaldos mit Chart.js.
// <canvas data-account-chart='{"labels":[…],"values":[…|null],"label":"…"}'>
// Chart.js wird wie bei den Dashboard-Statistiken zuerst lokal gesucht
// (public/assets/vendor/chartjs/chart.umd.min.js), sonst vom CDN geladen.
(function () {
  'use strict';

  var canvas = document.querySelector('canvas[data-account-chart]');
  if (!canvas) { return; }
  var LOCAL = '/assets/vendor/chartjs/chart.umd.min.js';
  var CDN = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';

  function load(src, ok, fail) {
    var s = document.createElement('script');
    s.src = src; s.async = true; s.onload = ok; s.onerror = fail;
    document.head.appendChild(s);
  }

  function unavailable() {
    var note = document.createElement('div');
    note.textContent = 'Diagramm konnte nicht geladen werden (Chart.js nicht erreichbar).';
    note.style.cssText = 'display:flex;align-items:center;justify-content:center;height:100%;font-size:.75rem;color:#94a3b8;text-align:center';
    canvas.parentNode.replaceChild(note, canvas);
  }

  function brand() {
    var raw = getComputedStyle(document.documentElement).getPropertyValue('--color-brand-500').trim();
    var parts = raw.split(/[\s,]+/).filter(Boolean);
    return parts.length === 3 ? parts.join(',') : '37,99,235';
  }

  function draw() {
    var spec;
    try { spec = JSON.parse(canvas.getAttribute('data-account-chart')); } catch (e) { return; }
    var dark = document.documentElement.classList.contains('dark');
    var text = dark ? '#cbd5e1' : '#475569';
    var grid = dark ? 'rgba(148,163,184,.15)' : 'rgba(148,163,184,.25)';
    var rgb = brand();
    var money = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' });
    var short = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', notation: 'compact', maximumFractionDigits: 1 });
    var many = spec.labels.length > 60;

    var chart = new window.Chart(canvas, {
      type: 'line',
      data: {
        labels: spec.labels,
        datasets: [{
          label: spec.label,
          data: spec.values,
          borderColor: 'rgb(' + rgb + ')',
          backgroundColor: 'rgba(' + rgb + ',0.12)',
          fill: 'origin',
          borderWidth: 2,
          pointRadius: many ? 0 : 2,
          pointHoverRadius: 4,
          tension: 0.25,
          stepped: false,
          spanGaps: false,
          segment: {
            borderColor: function (ctx) { return ctx.p1.parsed.y < 0 ? '#dc2626' : undefined; }
          }
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 300 },
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: function (c) { return c.parsed.y === null ? 'kein Saldo' : 'Saldo: ' + money.format(c.parsed.y); } } }
        },
        scales: {
          x: { ticks: { color: text, autoSkip: true, maxTicksLimit: 12, maxRotation: 0 }, grid: { display: false } },
          y: { ticks: { color: text, callback: function (v) { return short.format(v); } }, grid: { color: grid } }
        }
      }
    });
    return chart;
  }

  if (window.Chart) { draw(); return; }
  load(LOCAL, draw, function () { load(CDN, draw, unavailable); });
})();
