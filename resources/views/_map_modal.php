<?php
/**
 * Modal mit OpenStreetMap-Karte (Nutzerwunsch): einmal pro Seite
 * eingebunden; Auslöser sind Elemente mit data-map-modal, data-lat,
 * data-lon und optional data-title / data-address (z. B. das Badge
 * „Koordinaten vorhanden“ in den Listen der Veranstaltungsorte und Lager).
 */

if (!empty($GLOBALS['__mapModalRendered'])) {
    return;
}
$GLOBALS['__mapModalRendered'] = true;
?>
<dialog id="map-modal" class="modal-panel modal-panel-lg rounded-lg border border-slate-200 bg-white p-0 dark:border-slate-700 dark:bg-slate-900" aria-labelledby="map-modal-title">
    <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
        <div class="min-w-0">
            <h2 id="map-modal-title" class="truncate text-base font-semibold text-slate-900 dark:text-slate-100"></h2>
            <p id="map-modal-address" class="text-xs text-slate-500 dark:text-slate-400"></p>
        </div>
        <button type="button" data-map-modal-close class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Schließen</button>
    </div>
    <iframe id="map-modal-frame" title="Karte" class="block w-full" style="border:0;height:min(65vh,32rem)" loading="lazy"></iframe>
    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2 text-xs">
        <a id="map-modal-osm" href="#" target="_blank" rel="noopener" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Größere Karte auf openstreetmap.org anzeigen</a>
        <a id="map-modal-route" href="#" target="_blank" rel="noopener" class="font-medium text-brand-700 hover:underline dark:text-brand-400">Route in Google Maps</a>
    </div>
</dialog>
<script>
(function () {
    var dialog = document.getElementById('map-modal');
    var frame = document.getElementById('map-modal-frame');
    if (!dialog || !frame) { return; }
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-map-modal]');
        if (trigger) {
            event.preventDefault();
            var lat = parseFloat(trigger.getAttribute('data-lat'));
            var lon = parseFloat(trigger.getAttribute('data-lon'));
            if (isNaN(lat) || isNaN(lon)) { return; }
            var d = 0.006;
            var bbox = [lon - d, lat - d, lon + d, lat + d].join(',');
            frame.src = 'https://www.openstreetmap.org/export/embed.html?bbox=' + encodeURIComponent(bbox) + '&layer=mapnik&marker=' + encodeURIComponent(lat + ',' + lon);
            document.getElementById('map-modal-title').textContent = trigger.getAttribute('data-title') || 'Karte';
            document.getElementById('map-modal-address').textContent = trigger.getAttribute('data-address') || '';
            document.getElementById('map-modal-osm').href = 'https://www.openstreetmap.org/?mlat=' + lat + '&mlon=' + lon + '#map=16/' + lat + '/' + lon;
            document.getElementById('map-modal-route').href = 'https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lon;
            dialog.showModal();
            return;
        }
        if (event.target.closest('[data-map-modal-close]') || event.target === dialog) {
            dialog.close();
        }
    });
    dialog.addEventListener('close', function () { frame.src = 'about:blank'; });
})();
</script>
