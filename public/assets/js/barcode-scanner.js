/*!
 * barcode-scanner.js — Kamera-basierter Code-128-Barcode-Scanner für die
 * Ausgabe-/Rücknahme-Checklisten (siehe resources/views/rentals/
 * checklist.php und return-checklist.php).
 *
 * Warum eine eigene Implementierung statt einer vendorten Bibliothek
 * (z. B. @zxing/library)?
 * In dieser Entwicklungsumgebung ist der Zugriff auf die npm-Registry
 * (registry.npmjs.org) sowie auf alle gängigen CDN-Hosts (cdn.jsdelivr.net,
 * unpkg.com, cdnjs.cloudflare.com) durch die Egress-Policy der Sandbox
 * blockiert (403 auf jeden CONNECT-Versuch) — analog zum bereits
 * bestehenden Hinweis in src/Infrastructure/Mail/SmtpMailer.php zu
 * Packagist/Composer, hier zusätzlich auch für npm/CDN. "npm install"
 * konnte daher nicht ausgeführt werden. Diese Datei ist deshalb eine
 * schlanke, abhängigkeitsfreie Eigenimplementierung des Code-128-Decoders
 * (Zeichensatz B), die exakt dieselbe Musterliste (PATTERNS) verwendet wie
 * der Server-seitige Encoder in src/Infrastructure/Printing/
 * Code128Encoder.php, der die gedruckten Etiketten erzeugt — beide Seiten
 * sind damit garantiert kompatibel. Falls in einer produktiven Umgebung
 * npm-Zugriff besteht, kann diese Datei jederzeit 1:1 durch einen Build aus
 * @zxing/library ersetzt werden, ohne dass sich die Aufruf-Schnittstelle
 * (window.BarcodeScanner.attach(...)) ändern müsste.
 *
 * Lizenz: MIT (dieses Projekt), keine externen Abhängigkeiten enthalten.
 *
 * -------------------------------------------------------------------------
 * Funktionsweise (kurz):
 *  1. getUserMedia() öffnet die Rückkamera (facingMode "environment") in
 *     einem <video>-Element.
 *  2. Per requestAnimationFrame wird ca. alle 120 ms ein Kamera-Einzelbild
 *     auf einen unsichtbaren <canvas> gezeichnet (verkleinert auf max.
 *     480px Breite, für Performance).
 *  3. Aus mehreren horizontalen Bildzeilen wird je ein Helligkeits-Array
 *     extrahiert, per einfachem Schwellenwert (Min/Max-Mitte der Zeile) zu
 *     Schwarz/Weiß binarisiert und in Strich-/Lückenbreiten (Run-Length-
 *     Encoding) zerlegt.
 *  4. decodeRunsToText() sucht in den Breiten ein gültiges Code-128-
 *     Start-Muster (Start B, ggf. Start A/C), dekodiert die folgenden
 *     11-Modul-Symbole anhand von PATTERNS, prüft die Modulo-103-
 *     Prüfsumme (siehe Code128Encoder::encodeSymbols()) und verlangt ein
 *     gültiges Stop-Muster. Nur bei bestandener Prüfsumme gilt ein Treffer
 *     als sicher (verhindert Fehlerkennungen durch Bildrauschen).
 *  5. Bei Erfolg: Wert in #scan-input einsetzen, Formular exakt wie bei
 *     manueller Eingabe/Handscanner per form.requestSubmit() absenden
 *     (triggert denselben globalen "submit"-Listener aus layout.php, der
 *     ggf. vorhandene data-confirm-Bestätigungen übernimmt), Kamera stoppen
 *     und Dialog schließen.
 * -------------------------------------------------------------------------
 */
(function (window, document) {
  'use strict';

  // Identische Musterliste wie Code128Encoder::PATTERNS (Index = Symbolwert
  // 0-106, Wert = Strichbreiten-Folge in Modulen als Ziffernfolge).
  var PATTERNS = [
    '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
    '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
    '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
    '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
    '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
    '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
    '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
    '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
    '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
    '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
    '114131', '311141', '411131', '211412', '211214', '211232', '2331112'
  ];
  var START_A = 103;
  var START_B = 104;
  var START_C = 105;
  var STOP = 106;
  var START_VALUES = [START_A, START_B, START_C];

  // Reverse-Lookup Muster -> Symbolwert, getrennt nach Länge (6 bzw. 7
  // Ziffern), da JS keine Tupel-Keys kennt und beide Musterlängen
  // vorkommen (Datensymbole: 6 Ziffern/11 Module, Stop-Symbol: 7 Ziffern/13
  // Module).
  var PATTERN_TO_VALUE = {};
  for (var i = 0; i < PATTERNS.length; i++) {
    PATTERN_TO_VALUE[PATTERNS[i]] = i;
  }

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  /**
   * Wandelt eine Folge von Pixel-Breiten (runs) und eine geschätzte
   * Modulbreite in eine Ziffernfolge ("Strichmuster") um, wie sie in
   * PATTERNS vorkommt. Rundet jede Breite auf die nächstliegende Modulzahl
   * (1-4).
   */
  function widthsToPattern(widths, moduleWidth) {
    var out = '';
    for (var i = 0; i < widths.length; i++) {
      var digit = clamp(Math.round(widths[i] / moduleWidth), 1, 4);
      out += String(digit);
    }
    return out;
  }

  /**
   * Zerlegt eine binarisierte Bildzeile (Array aus 0/1, 1 = dunkel/Strich)
   * in alternierende Lauflängen. Das erste Element im Ergebnis beschreibt
   * immer eine "helle" (Lücke/Quietzone) oder "dunkle" (Strich) Region, je
   * nach tatsächlichem Zeileninhalt — color gibt das explizit an.
   *
   * @return {Array<{color: number, length: number}>}
   */
  function toRuns(bits) {
    var runs = [];
    if (bits.length === 0) {
      return runs;
    }
    var currentColor = bits[0];
    var length = 1;
    for (var i = 1; i < bits.length; i++) {
      if (bits[i] === currentColor) {
        length++;
      } else {
        runs.push({ color: currentColor, length: length });
        currentColor = bits[i];
        length = 1;
      }
    }
    runs.push({ color: currentColor, length: length });
    return runs;
  }

  /**
   * Versucht, aus einer Folge von Strich-/Lückenbreiten (runs, beginnend
   * mit einem beliebigen Farbwert) einen gültigen Code-128-Text zu
   * dekodieren. Gibt den dekodierten String zurück oder null.
   */
  function decodeRuns(runs) {
    // Nur an einem SCHWARZEN Run kann ein Start-Symbol beginnen.
    for (var startIdx = 0; startIdx < runs.length - 6; startIdx++) {
      if (runs[startIdx].color !== 1) {
        continue;
      }

      var startWidths = [];
      for (var w = 0; w < 6; w++) {
        startWidths.push(runs[startIdx + w].length);
      }
      var startSum = startWidths.reduce(function (a, b) { return a + b; }, 0);
      var moduleWidth = startSum / 11;
      if (moduleWidth < 0.6) {
        // Unrealistisch schmal (Bildrauschen) — verwerfen.
        continue;
      }

      var startPattern = widthsToPattern(startWidths, moduleWidth);
      var startValue = PATTERN_TO_VALUE.hasOwnProperty(startPattern) ? PATTERN_TO_VALUE[startPattern] : null;
      if (startValue === null || START_VALUES.indexOf(startValue) === -1) {
        continue;
      }
      // Nur Zeichensatz B wird server-seitig zum Drucken verwendet (siehe
      // Code128Encoder-Kommentar) — Start A/C werden zwar erkannt, aber die
      // Symboltabelle unten deckt ausschließlich Zeichensatz B ab.
      if (startValue !== START_B) {
        continue;
      }

      var symbols = [startValue];
      var pos = startIdx + 6;
      var checksum = startValue;
      var dataCount = 0;
      var found = false;

      // Maximal 40 Datensymbole (mehr als genug für jede realistische
      // Inventarnummer) — verhindert Endlosschleifen bei Fehlmustern.
      while (dataCount < 40 && pos + 7 <= runs.length) {
        // Zuerst prüfen, ob an dieser Position bereits das Stop-Muster
        // (7 Läufe / 13 Module) beginnt — dann ist die Sequenz komplett.
        var stopWidths = [];
        for (var s = 0; s < 7; s++) {
          stopWidths.push(runs[pos + s].length);
        }
        var stopSum = stopWidths.reduce(function (a, b) { return a + b; }, 0);
        var stopModuleWidth = stopSum / 13;
        if (stopModuleWidth >= 0.6) {
          var stopPattern = widthsToPattern(stopWidths, stopModuleWidth);
          if (PATTERN_TO_VALUE.hasOwnProperty(stopPattern) && PATTERN_TO_VALUE[stopPattern] === STOP) {
            found = true;
            break;
          }
        }

        if (pos + 6 > runs.length) {
          break;
        }
        var symWidths = [];
        for (var d = 0; d < 6; d++) {
          symWidths.push(runs[pos + d].length);
        }
        var symSum = symWidths.reduce(function (a, b) { return a + b; }, 0);
        var symModuleWidth = symSum / 11;
        if (symModuleWidth < 0.6) {
          break;
        }
        var symPattern = widthsToPattern(symWidths, symModuleWidth);
        if (!PATTERN_TO_VALUE.hasOwnProperty(symPattern)) {
          break;
        }
        var symValue = PATTERN_TO_VALUE[symPattern];
        if (symValue === STOP || START_VALUES.indexOf(symValue) !== -1) {
          // Ein Start-/Stop-Wert mitten in den Datensymbolen ist ungültig.
          break;
        }

        symbols.push(symValue);
        dataCount++;
        checksum += symValue * dataCount;
        pos += 6;
      }

      if (!found || symbols.length < 2) {
        continue;
      }

      // Letztes gesammeltes Symbol ist das Prüfsymbol, alles davor sind
      // Datensymbole (siehe Code128Encoder::encodeSymbols()).
      var checkSymbol = symbols[symbols.length - 1];
      var dataSymbols = symbols.slice(1, symbols.length - 1);

      var expectedChecksum = startValue;
      for (var p = 0; p < dataSymbols.length; p++) {
        expectedChecksum += dataSymbols[p] * (p + 1);
      }
      expectedChecksum = expectedChecksum % 103;

      if (expectedChecksum !== checkSymbol || dataSymbols.length === 0) {
        continue;
      }

      var text = '';
      var validText = true;
      for (var v = 0; v < dataSymbols.length; v++) {
        var value = dataSymbols[v];
        if (value < 0 || value > 95) {
          // FNC-/Shift-Symbole (96+) werden von unseren Etiketten nicht
          // erzeugt (siehe Code128Encoder) — als nicht dekodierbar werten.
          validText = false;
          break;
        }
        text += String.fromCharCode(value + 32);
      }

      if (!validText || text === '') {
        continue;
      }

      return text;
    }

    return null;
  }

  /**
   * Nimmt eine Graustufen-Bildzeile (Uint8ClampedArray/Array), binarisiert
   * sie per einfachem Mittelwert-Schwellenwert und versucht, daraus einen
   * Code-128-Text zu dekodieren — sowohl vorwärts als auch rückwärts
   * (Barcode kann seitenverkehrt vor die Kamera gehalten werden).
   */
  function decodeGrayscaleRow(gray) {
    var min = 255;
    var max = 0;
    for (var i = 0; i < gray.length; i++) {
      if (gray[i] < min) min = gray[i];
      if (gray[i] > max) max = gray[i];
    }
    // Zu geringer Kontrast in dieser Zeile (z. B. kein Barcode im Bild) —
    // gar nicht erst versuchen, spart Rechenzeit und vermeidet Zufallstreffer.
    if (max - min < 40) {
      return null;
    }
    var threshold = (min + max) / 2;

    var bits = new Array(gray.length);
    for (var j = 0; j < gray.length; j++) {
      bits[j] = gray[j] < threshold ? 1 : 0;
    }

    var runs = toRuns(bits);
    var forward = decodeRuns(runs);
    if (forward !== null) {
      return forward;
    }

    var reversedRuns = runs.slice().reverse();
    return decodeRuns(reversedRuns);
  }

  // ---------------------------------------------------------------------
  // Kamera-/UI-Steuerung
  // ---------------------------------------------------------------------

  /**
   * Bindet einen Scan-Button + Dialog an das Kamera-Scan-Verhalten.
   *
   * options:
   *  - buttonId: Button, der den Dialog öffnet
   *  - dialogId: <dialog>-Element
   *  - videoId: <video>-Element im Dialog
   *  - flashId: Element für das kurze grüne Erfolgs-Aufblitzen (optional)
   *  - errorId: Element für Fehlermeldungen (Text wird hineingeschrieben,
   *             "hidden"-Klasse wird an/aus geschaltet)
   *  - cancelId: "Abbrechen"-Button im Dialog
   *  - inputId: bestehendes Scan-Textfeld (#scan-input), das befüllt wird
   *  - messages: { permission, noCamera, insecure, generic } — lokalisierte
   *              Fehlertexte (aus t(), siehe Aufrufer in den Views)
   */
  function attach(options) {
    var button = document.getElementById(options.buttonId);
    var dialog = document.getElementById(options.dialogId);
    var video = document.getElementById(options.videoId);
    var flashEl = options.flashId ? document.getElementById(options.flashId) : null;
    var errorEl = options.errorId ? document.getElementById(options.errorId) : null;
    var cancelBtn = options.cancelId ? document.getElementById(options.cancelId) : null;
    var input = document.getElementById(options.inputId);
    var messages = options.messages || {};

    if (!button || !dialog || !video || !input) {
      return;
    }

    var stream = null;
    var canvas = document.createElement('canvas');
    var ctx = canvas.getContext('2d', { willReadFrequently: true });
    var rafHandle = null;
    var lastScanAt = 0;
    var SCAN_INTERVAL_MS = 120;
    var stopped = true;

    function showError(message) {
      if (!errorEl) {
        return;
      }
      errorEl.textContent = message;
      errorEl.classList.remove('hidden');
    }

    function hideError() {
      if (!errorEl) {
        return;
      }
      errorEl.textContent = '';
      errorEl.classList.add('hidden');
    }

    function stopCamera() {
      stopped = true;
      if (rafHandle !== null) {
        window.cancelAnimationFrame(rafHandle);
        rafHandle = null;
      }
      if (stream) {
        // WICHTIG: alle Tracks explizit stoppen, sonst bleibt die
        // Kamera-LED des Smartphones aktiv, auch wenn der Dialog
        // geschlossen ist.
        stream.getTracks().forEach(function (track) {
          track.stop();
        });
        stream = null;
      }
      video.srcObject = null;
    }

    function flashSuccess() {
      if (!flashEl) {
        return;
      }
      flashEl.classList.remove('hidden');
      window.setTimeout(function () {
        flashEl.classList.add('hidden');
      }, 350);
    }

    function submitScannedValue(value) {
      input.value = value;
      flashSuccess();

      var form = input.closest('form');

      window.setTimeout(function () {
        stopCamera();
        if (dialog.open) {
          dialog.close();
        }
        if (form && typeof form.requestSubmit === 'function') {
          // requestSubmit() löst denselben "submit"-Event wie ein Absenden
          // per Enter/Klick aus — inkl. des globalen data-confirm-Handlers
          // aus layout.php, falls das Formular ein solches Attribut trägt.
          // Die Scan-Formulare hier tragen aktuell kein data-confirm, ein
          // Handler ist dennoch identisch kompatibel.
          form.requestSubmit();
        } else if (form) {
          form.submit();
        }
      }, 300);
    }

    function processFrame(timestamp) {
      if (stopped) {
        return;
      }
      rafHandle = window.requestAnimationFrame(processFrame);

      if (timestamp - lastScanAt < SCAN_INTERVAL_MS) {
        return;
      }
      lastScanAt = timestamp;

      if (video.readyState < video.HAVE_CURRENT_DATA || video.videoWidth === 0) {
        return;
      }

      var scale = Math.min(1, 480 / video.videoWidth);
      var width = Math.max(1, Math.round(video.videoWidth * scale));
      var height = Math.max(1, Math.round(video.videoHeight * scale));
      if (canvas.width !== width || canvas.height !== height) {
        canvas.width = width;
        canvas.height = height;
      }
      ctx.drawImage(video, 0, 0, width, height);

      // Mehrere horizontale Zeilen über die Bildhöhe verteilt versuchen,
      // da der Barcode nicht zwangsläufig exakt mittig gehalten wird.
      var rowFractions = [0.35, 0.45, 0.5, 0.55, 0.65];
      for (var r = 0; r < rowFractions.length; r++) {
        var y = clamp(Math.round(height * rowFractions[r]), 0, height - 1);
        var imageData;
        try {
          imageData = ctx.getImageData(0, y, width, 1);
        } catch (e) {
          // getImageData kann z. B. bei verunreinigtem Canvas (Cross-Origin)
          // fehlschlagen — sollte hier nicht vorkommen (lokaler Kamerastream),
          // aber defensiv abfangen statt die App abstürzen zu lassen.
          return;
        }
        var data = imageData.data;
        var gray = new Array(width);
        for (var x = 0; x < width; x++) {
          var idx = x * 4;
          // Standard-Luminanzformel (Rec. 601).
          gray[x] = (0.299 * data[idx] + 0.587 * data[idx + 1] + 0.114 * data[idx + 2]) | 0;
        }

        var decoded = decodeGrayscaleRow(gray);
        if (decoded) {
          submitScannedValue(decoded);
          return;
        }
      }
    }

    function startCamera() {
      hideError();

      if (!window.isSecureContext) {
        showError(messages.insecure || 'Kamerazugriff benötigt eine sichere (HTTPS-)Verbindung.');
        return;
      }
      if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        showError(messages.generic || 'Kamera konnte nicht gestartet werden.');
        return;
      }

      navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false
      }).then(function (mediaStream) {
        stream = mediaStream;
        video.srcObject = mediaStream;
        stopped = false;
        var playPromise = video.play();
        if (playPromise && typeof playPromise.catch === 'function') {
          playPromise.catch(function () {
            // Autoplay kann in seltenen Fällen blockiert werden — Kamera
            // läuft trotzdem, sobald der Nutzer den Dialog fokussiert.
          });
        }
        lastScanAt = 0;
        rafHandle = window.requestAnimationFrame(processFrame);
      }).catch(function (err) {
        var name = err && err.name ? err.name : '';
        if (name === 'NotAllowedError' || name === 'SecurityError') {
          showError(messages.permission || 'Kamerazugriff wurde verweigert.');
        } else if (name === 'NotFoundError' || name === 'OverconstrainedError') {
          showError(messages.noCamera || 'Keine Kamera gefunden.');
        } else {
          showError(messages.generic || 'Kamera konnte nicht gestartet werden.');
        }
      });
    }

    button.addEventListener('click', function () {
      hideError();
      if (typeof dialog.showModal === 'function') {
        dialog.showModal();
      } else {
        dialog.setAttribute('open', 'open');
      }
      startCamera();
    });

    if (cancelBtn) {
      cancelBtn.addEventListener('click', function () {
        stopCamera();
        if (dialog.open) {
          dialog.close();
        }
      });
    }

    // Schließen per Escape-Taste oder Klick auf das Backdrop feuert das
    // native "close"-Event des <dialog> — Kamera dort ebenfalls stoppen.
    dialog.addEventListener('close', function () {
      stopCamera();
    });

    // Falls die Seite verlassen/neu geladen wird, während die Kamera noch
    // läuft (z. B. Navigation ohne vorheriges Schließen des Dialogs).
    window.addEventListener('pagehide', stopCamera);
    window.addEventListener('beforeunload', stopCamera);
  }

  window.BarcodeScanner = {
    attach: attach,
    // Für Tests/Diagnose im Browser zugänglich, z. B. um einen gerenderten
    // Barcode aus einem <canvas> ohne Kamera zu dekodieren.
    _decodeGrayscaleRow: decodeGrayscaleRow
  };
})(window, document);
