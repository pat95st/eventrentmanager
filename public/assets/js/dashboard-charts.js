// Diagramme der Dashboard-Statistiken (Chart.js).
// Jedes <canvas data-chart='{"type":…,"labels":[…],"datasets":[…],"currency":bool}'>
// wird gezeichnet. Chart.js wird zuerst lokal gesucht
// (public/assets/vendor/chartjs/chart.umd.min.js), sonst vom CDN geladen.
(function () {
  'use strict';

  var LOCAL = '/assets/vendor/chartjs/chart.umd.min.js';
  var CDN = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js';

  var canvases = document.querySelectorAll('canvas[data-chart]');
  if (!canvases.length) {
    return;
  }

  function loadScript(src, onload, onerror) {
    var script = document.createElement('script');
    script.src = src;
    script.async = true;
    script.onload = onload;
    script.onerror = onerror;
    document.head.appendChild(script);
  }

  function loadChartJs(callback) {
    if (window.Chart) {
      callback();
      return;
    }
    if (window.__chartJsLoading) {
      window.__chartJsLoading.push(callback);
      return;
    }
    window.__chartJsLoading = [callback];
    var done = function () {
      var queue = window.__chartJsLoading || [];
      window.__chartJsLoading = null;
      queue.forEach(function (cb) { cb(); });
    };
    loadScript(LOCAL, done, function () {
      loadScript(CDN, done, function () {
        window.__chartJsLoading = null;
        showUnavailable();
      });
    });
  }

  function showUnavailable() {
    canvases.forEach(function (canvas) {
      var box = canvas.parentNode;
      var note = document.createElement('div');
      note.textContent = 'Diagramm konnte nicht geladen werden (Chart.js nicht erreichbar).';
      note.style.cssText = 'display:flex;align-items:center;justify-content:center;height:100%;font-size:.75rem;color:#94a3b8;text-align:center';
      box.replaceChild(note, canvas);
    });
  }

  function isDark() {
    return document.documentElement.classList.contains('dark');
  }

  function brandColor() {
    var raw = getComputedStyle(document.documentElement).getPropertyValue('--color-brand-500').trim();
    var parts = raw.split(/[\s,]+/).filter(Boolean);
    return parts.length === 3 ? 'rgb(' + parts.join(',') + ')' : '#2563eb';
  }

  // 0 Marke, 1 grün, 2 gelb, 3 rot, 4 orange, 5 grau, 6 violett, 7 türkis
  function palette() {
    return [brandColor(), '#10b981', '#f59e0b', '#f43f5e', '#ea580c', isDark() ? '#64748b' : '#94a3b8', '#8b5cf6', '#14b8a6'];
  }

  function withAlpha(color, alpha) {
    if (color.indexOf('rgb(') === 0) {
      return color.replace('rgb(', 'rgba(').replace(')', ',' + alpha + ')');
    }
    var hex = color.replace('#', '');
    var r = parseInt(hex.substring(0, 2), 16);
    var g = parseInt(hex.substring(2, 4), 16);
    var b = parseInt(hex.substring(4, 6), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  var numberFormat = new Intl.NumberFormat('de-DE');
  var currencyFormat = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR' });
  var currencyShort = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'EUR', notation: 'compact', maximumFractionDigits: 1 });

  function build(canvas) {
    var spec;
    try {
      spec = JSON.parse(canvas.getAttribute('data-chart'));
    } catch (e) {
      return;
    }
    var compact = canvas.getAttribute('data-chart-compact') === '1';
    var colors = palette();
    var dark = isDark();
    var text = dark ? '#cbd5e1' : '#475569';
    var grid = dark ? 'rgba(148,163,184,.15)' : 'rgba(148,163,184,.25)';
    var doughnut = spec.type === 'doughnut';
    var horizontal = spec.type === 'barH';
    var format = function (v) { return spec.currency ? currencyFormat.format(v) : numberFormat.format(v); };

    var datasets = spec.datasets.map(function (ds, i) {
      var base = colors[(ds.color !== undefined ? ds.color : i) % colors.length];
      var out = { label: ds.label, data: ds.data };
      if (doughnut) {
        var list = ds.data.map(function (_, j) {
          return colors[((ds.colors && ds.colors[j] !== undefined) ? ds.colors[j] : j) % colors.length];
        });
        out.backgroundColor = list;
        out.borderColor = dark ? '#0f172a' : '#ffffff';
        out.borderWidth = 2;
        out.hoverOffset = 4;
      } else if (ds.type === 'line') {
        out.type = 'line';
        out.borderColor = base;
        out.backgroundColor = base;
        out.borderWidth = 2;
        out.borderDash = [4, 3];
        out.pointRadius = compact ? 0 : 2;
        out.tension = 0.3;
        out.order = 0;
      } else {
        out.backgroundColor = withAlpha(base, 0.8);
        out.hoverBackgroundColor = base;
        out.borderRadius = 3;
        out.maxBarThickness = horizontal ? 18 : 28;
        out.order = 1;
      }
      return out;
    });

    var config = {
      type: doughnut ? 'doughnut' : 'bar',
      data: { labels: spec.labels, datasets: datasets },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: { duration: 400 },
        plugins: {
          legend: {
            display: doughnut || datasets.length > 1,
            position: doughnut ? (compact ? 'right' : 'bottom') : 'bottom',
            labels: { color: text, boxWidth: 10, boxHeight: 10, font: { size: compact ? 10 : 12 }, padding: compact ? 6 : 12 }
          },
          tooltip: {
            callbacks: {
              label: function (ctx) {
                var value = doughnut ? ctx.parsed : (horizontal ? ctx.parsed.x : ctx.parsed.y);
                var name = doughnut ? ctx.label : ctx.dataset.label;
                return ' ' + (name ? name + ': ' : '') + format(value);
              }
            }
          }
        }
      }
    };

    if (doughnut) {
      config.options.cutout = '62%';
    } else {
      var valueAxis = {
        beginAtZero: true,
        grid: { color: grid },
        border: { display: false },
        ticks: {
          color: text,
          precision: spec.currency ? undefined : 0,
          maxTicksLimit: compact ? 4 : 8,
          callback: function (v) { return spec.currency ? (compact ? currencyShort.format(v) : currencyFormat.format(v)) : numberFormat.format(v); }
        }
      };
      var categoryAxis = {
        grid: { display: false },
        border: { color: grid },
        ticks: { color: text, autoSkip: true, maxRotation: 0, font: { size: compact ? 10 : 12 } }
      };
      if (horizontal) {
        config.options.indexAxis = 'y';
        config.options.scales = { x: valueAxis, y: categoryAxis };
        if (compact) {
          categoryAxis.ticks.callback = function (v) {
            var label = String(this.getLabelForValue(v));
            return label.length > 18 ? label.slice(0, 17) + '…' : label;
          };
        }
      } else {
        config.options.scales = { x: categoryAxis, y: valueAxis };
      }
    }

    canvas.__chart = new window.Chart(canvas, config);
  }

  function renderAll() {
    canvases.forEach(function (canvas) {
      if (canvas.__chart) {
        canvas.__chart.destroy();
      }
      build(canvas);
    });
  }

  loadChartJs(function () {
    renderAll();
    // Hell/Dunkel-Umschaltung: Farben neu setzen.
    var lastDark = isDark();
    new MutationObserver(function () {
      if (isDark() !== lastDark) {
        lastDark = isDark();
        renderAll();
      }
    })
      .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
  });
})();
