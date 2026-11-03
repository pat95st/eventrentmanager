// PDF-Ansicht innerhalb der installierten App (PWA).
//
// Problem: In der als App installierten Anwendung (display-mode standalone)
// gibt es keine Browser-Leiste. Öffnet man dort ein PDF (Rechnung, Angebot,
// Packliste, Case-Stückliste …), ersetzt es die App-Seite komplett — auf dem
// iPhone gibt es dann keinen Weg zurück, man muss die App schließen.
//
// Lösung: Links auf PDFs/Dateien werden in der App abgefangen und in einem
// Vollbild-Overlay mit „Zurück“-Leiste angezeigt (Darstellung mit pdf.js,
// public/assets/vendor/pdfjs). Bilder werden direkt angezeigt, andere
// Dateitypen lassen sich über „Teilen/Sichern“ speichern. Im normalen
// Browser-Tab bleibt alles wie bisher.
//
// Erkannt werden gleiche-Domain-Links, deren Pfad auf .pdf, /pdf, /download,
// /herunterladen oder ein RMA-Dokument endet, sowie alle Links/GET-Formulare
// mit data-pdf-viewer. data-no-viewer schließt einen Link aus.
(function () {
  'use strict';

  var standalone = window.navigator.standalone === true ||
    (window.matchMedia && (window.matchMedia('(display-mode: standalone)').matches ||
      window.matchMedia('(display-mode: fullscreen)').matches ||
      window.matchMedia('(display-mode: minimal-ui)').matches));
  if (!standalone) {
    return;
  }

  var PDFJS_URL = '/assets/vendor/pdfjs/pdf.min.js';
  var WORKER_URL = '/assets/vendor/pdfjs/pdf.worker.min.js';
  var FILE_PATH = /(\.pdf$|\/pdf$|\/download$|\/herunterladen$|\/rma\/[^/]+\/dokumente\/[^/]+$)/i;
  var ZOOM_STEPS = [1, 1.5, 2, 3];

  var overlay = null;
  var state = null;
  var pdfjsPromise = null;

  function wantsViewer(url, element) {
    if (element && element.hasAttribute('data-no-viewer')) {
      return false;
    }
    if (url.origin !== window.location.origin) {
      return false;
    }
    return (element && element.hasAttribute('data-pdf-viewer')) || FILE_PATH.test(url.pathname);
  }

  document.addEventListener('click', function (event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
      return;
    }
    var link = event.target.closest ? event.target.closest('a[href]') : null;
    if (!link) {
      return;
    }
    var url;
    try {
      url = new URL(link.href, window.location.href);
    } catch (e) {
      return;
    }
    if (!wantsViewer(url, link)) {
      return;
    }
    event.preventDefault();
    openViewer(url.href);
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (event.defaultPrevented || !(form instanceof HTMLFormElement) || (form.getAttribute('method') || 'get').toLowerCase() !== 'get') {
      return;
    }
    var url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
    if (!wantsViewer(url, form)) {
      return;
    }
    event.preventDefault();
    var data = event.submitter ? new FormData(form, event.submitter) : new FormData(form);
    url.search = new URLSearchParams(data).toString();
    // Ein Modal, aus dem das Formular kommt (z. B. Preisliste), schließen.
    var dialog = form.closest('dialog');
    if (dialog && dialog.open && typeof dialog.close === 'function') {
      dialog.close();
    }
    openViewer(url.href);
  });

  window.addEventListener('popstate', function () {
    if (overlay) {
      closeViewer();
    }
  });

  document.addEventListener('keydown', function (event) {
    if (overlay && event.key === 'Escape') {
      requestClose();
    }
  });

  function button(label, attrs) {
    var b = document.createElement('button');
    b.type = 'button';
    b.textContent = label;
    b.setAttribute('style', 'appearance:none;border:1px solid #cbd5e1;background:#fff;color:#0f172a;border-radius:0.375rem;padding:0.4rem 0.75rem;font-size:0.875rem;font-weight:500;line-height:1.2;white-space:nowrap;' + (attrs || ''));
    return b;
  }

  function buildOverlay() {
    overlay = document.createElement('div');
    overlay.id = 'pdf-viewer-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('style', 'position:fixed;inset:0;z-index:2147483000;display:flex;flex-direction:column;background:#475569;');

    var bar = document.createElement('div');
    bar.setAttribute('style', 'display:flex;align-items:center;gap:0.5rem;background:#fff;border-bottom:1px solid #e2e8f0;padding:0.5rem 0.75rem;padding-top:calc(0.5rem + env(safe-area-inset-top));padding-left:calc(0.75rem + env(safe-area-inset-left));padding-right:calc(0.75rem + env(safe-area-inset-right));');

    var back = button('← Zurück', 'border-color:#2563eb;background:#2563eb;color:#fff;');
    back.addEventListener('click', requestClose);

    var title = document.createElement('span');
    title.setAttribute('style', 'flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:0.875rem;color:#334155;');
    title.textContent = 'Wird geladen …';

    var zoomOut = button('−', 'padding:0.4rem 0.6rem;');
    zoomOut.setAttribute('aria-label', 'Verkleinern');
    zoomOut.addEventListener('click', function () { changeZoom(-1); });
    var zoomIn = button('+', 'padding:0.4rem 0.6rem;');
    zoomIn.setAttribute('aria-label', 'Vergrößern');
    zoomIn.addEventListener('click', function () { changeZoom(1); });

    var share = button('Teilen');
    share.disabled = true;
    share.addEventListener('click', shareFile);

    bar.appendChild(back);
    bar.appendChild(title);
    bar.appendChild(zoomOut);
    bar.appendChild(zoomIn);
    bar.appendChild(share);

    var pages = document.createElement('div');
    pages.setAttribute('style', 'flex:1;overflow:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;padding:0.75rem;padding-bottom:calc(0.75rem + env(safe-area-inset-bottom));');

    overlay.appendChild(bar);
    overlay.appendChild(pages);
    document.body.appendChild(overlay);
    document.documentElement.style.overflow = 'hidden';

    return { title: title, pages: pages, share: share, zoomIn: zoomIn, zoomOut: zoomOut };
  }

  function message(text, withSave) {
    state.ui.pages.innerHTML = '';
    var box = document.createElement('div');
    box.setAttribute('style', 'max-width:28rem;margin:2rem auto;background:#fff;border-radius:0.5rem;padding:1.25rem;font-size:0.9rem;color:#334155;text-align:center;');
    box.textContent = text;
    if (withSave) {
      var save = button('Datei sichern', 'display:block;margin:1rem auto 0;border-color:#2563eb;background:#2563eb;color:#fff;');
      save.addEventListener('click', shareFile);
      box.appendChild(save);
    }
    state.ui.pages.appendChild(box);
  }

  function filenameFrom(response, url) {
    var header = response.headers.get('Content-Disposition') || '';
    var match = /filename\*=(?:UTF-8'')?([^;]+)/i.exec(header) || /filename="?([^";]+)"?/i.exec(header);
    var name = match ? match[1].trim() : decodeURIComponent(new URL(url).pathname.split('/').pop() || 'Datei');
    try {
      name = decodeURIComponent(name);
    } catch (e) { /* Name unverändert lassen */ }
    return name || 'Datei';
  }

  function openViewer(url) {
    if (overlay) {
      closeViewer();
    }
    state = { url: url, ui: buildOverlay(), blob: null, filename: 'Datei', pdf: null, zoom: 0, renderToken: 0 };
    history.pushState({ pdfViewer: true }, '');
    state.ui.pages.innerHTML = '';
    message('Wird geladen …', false);

    var current = state;
    fetch(url, { credentials: 'same-origin' }).then(function (response) {
      if (!response.ok) {
        throw new Error('Die Datei konnte nicht geladen werden (Fehler ' + response.status + ').');
      }
      current.filename = filenameFrom(response, url);
      var type = (response.headers.get('Content-Type') || '').toLowerCase();
      return response.blob().then(function (blob) {
        return { blob: blob, type: type };
      });
    }).then(function (result) {
      if (current !== state) {
        return;
      }
      current.blob = result.blob;
      current.ui.title.textContent = current.filename;
      current.ui.share.disabled = false;

      if (result.type.indexOf('pdf') !== -1) {
        return showPdf(current);
      }
      if (result.type.indexOf('image/') === 0) {
        current.ui.pages.innerHTML = '';
        var img = document.createElement('img');
        img.src = URL.createObjectURL(result.blob);
        img.alt = current.filename;
        img.setAttribute('style', 'display:block;max-width:100%;margin:0 auto;background:#fff;');
        current.ui.pages.appendChild(img);
        current.objectUrl = img.src;
        return;
      }
      if (result.type.indexOf('text/html') !== -1) {
        // Server hat statt der Datei eine Seite geliefert (z. B. Fehler-
        // oder Anmeldeseite): normal dorthin navigieren.
        closeViewer();
        window.location.replace(url);
        return;
      }
      message('Dieser Dateityp kann hier nicht angezeigt werden.', true);
    }).catch(function (error) {
      if (current === state) {
        message(error && error.message ? error.message : 'Die Datei konnte nicht angezeigt werden.', !!current.blob);
      }
    });
  }

  function loadPdfjs() {
    if (!pdfjsPromise) {
      pdfjsPromise = import(PDFJS_URL).then(function (pdfjs) {
        pdfjs.GlobalWorkerOptions.workerSrc = WORKER_URL;
        return pdfjs;
      });
      pdfjsPromise.catch(function () { pdfjsPromise = null; });
    }
    return pdfjsPromise;
  }

  function showPdf(current) {
    return Promise.all([loadPdfjs(), current.blob.arrayBuffer()]).then(function (results) {
      var pdfjs = results[0];
      current.task = pdfjs.getDocument({ data: new Uint8Array(results[1]), isEvalSupported: false });
      return current.task.promise;
    }).then(function (pdf) {
      if (current !== state) {
        destroyTask(current);
        return;
      }
      current.pdf = pdf;
      return renderPages(current);
    });
  }

  function renderPages(current) {
    var token = ++current.renderToken;
    var container = current.ui.pages;
    container.innerHTML = '';
    var available = Math.max(200, container.clientWidth - 24);
    var cssWidth = available * ZOOM_STEPS[current.zoom];
    var ratio = Math.min(window.devicePixelRatio || 1, 2);
    current.ui.zoomOut.disabled = current.zoom === 0;
    current.ui.zoomIn.disabled = current.zoom === ZOOM_STEPS.length - 1;

    var chain = Promise.resolve();
    for (var n = 1; n <= current.pdf.numPages; n++) {
      (function (pageNumber) {
        var canvas = document.createElement('canvas');
        canvas.setAttribute('style', 'display:block;margin:0 auto 0.75rem;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);');
        container.appendChild(canvas);
        chain = chain.then(function () {
          if (token !== current.renderToken || current !== state) {
            return;
          }
          return current.pdf.getPage(pageNumber).then(function (page) {
            var base = page.getViewport({ scale: 1 });
            var scale = cssWidth / base.width;
            // iOS begrenzt die Canvas-Fläche: bei starkem Zoom Auflösung drosseln.
            var pixelRatio = Math.min(ratio, Math.sqrt(16000000 / (base.width * scale * base.height * scale)));
            var viewport = page.getViewport({ scale: scale * pixelRatio });
            canvas.width = Math.floor(viewport.width);
            canvas.height = Math.floor(viewport.height);
            canvas.style.width = Math.floor(base.width * scale) + 'px';
            canvas.style.height = Math.floor(base.height * scale) + 'px';
            return page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
          });
        });
      })(n);
    }
    return chain;
  }

  function changeZoom(direction) {
    if (!state || !state.pdf) {
      return;
    }
    var next = Math.max(0, Math.min(ZOOM_STEPS.length - 1, state.zoom + direction));
    if (next !== state.zoom) {
      state.zoom = next;
      renderPages(state);
    }
  }

  function shareFile() {
    if (!state || !state.blob) {
      return;
    }
    var file = null;
    try {
      file = new File([state.blob], state.filename, { type: state.blob.type || 'application/octet-stream' });
    } catch (e) { /* ältere Browser ohne File-Konstruktor */ }

    if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
      navigator.share({ files: [file], title: state.filename }).catch(function () { /* abgebrochen */ });
      return;
    }
    var link = document.createElement('a');
    link.href = URL.createObjectURL(state.blob);
    link.download = state.filename;
    document.body.appendChild(link);
    link.click();
    setTimeout(function () {
      URL.revokeObjectURL(link.href);
      link.remove();
    }, 10000);
  }

  function requestClose() {
    if (history.state && history.state.pdfViewer) {
      history.back(); // popstate schließt das Overlay
    } else {
      closeViewer();
    }
  }

  function destroyTask(entry) {
    try {
      if (entry.task && typeof entry.task.destroy === 'function') {
        entry.task.destroy();
      }
    } catch (e) { /* bereits beendet */ }
  }

  function closeViewer() {
    if (state) {
      state.renderToken++;
      destroyTask(state);
      if (state.objectUrl) {
        URL.revokeObjectURL(state.objectUrl);
      }
    }
    if (overlay) {
      overlay.remove();
    }
    overlay = null;
    state = null;
    document.documentElement.style.overflow = '';
  }
})();
