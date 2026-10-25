<?php
/** @var \App\Application\Rental\Query\RentalCustomerChecklistView $checklist */
/** @var string $token */

// Hinweis: die Daten-Variable heißt bewusst NICHT "$view" — View::renderFile()
// hat selbst einen gleichnamigen Parameter, und extract(..., EXTR_SKIP)
// würde einen per data['view'] übergebenen Wert dadurch stillschweigend
// verwerfen (siehe App\Infrastructure\Http\View).

use App\Infrastructure\Http\Csrf;
use function App\Infrastructure\Http\e;

$rental = $checklist->rental;
$confirmedCount = count(array_filter($checklist->items, static fn ($item) => $item->issuedItem->confirmedByCustomerAt !== null));
$totalCount = count($checklist->items);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkliste – <?= e($rental->eventName) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script>
        window.CSRF_TOKEN = <?= json_encode(Csrf::token(), JSON_THROW_ON_ERROR) ?>;
    </script>
</head>
<body class="min-h-screen bg-slate-50 antialiased">
    <div class="mx-auto max-w-xl px-4 py-10">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-6">
                <p class="text-xs font-medium uppercase tracking-wide text-brand-600">Vollständigkeits-Checkliste</p>
                <h1 class="mt-1 text-xl font-semibold text-slate-900"><?= e($rental->eventName) ?></h1>
                <p class="mt-1 text-sm text-slate-500">
                    <?= e($checklist->customer->displayName()) ?>
                    · <?= e($rental->startDate->format('d.m.Y')) ?> – <?= e($rental->endDate->format('d.m.Y')) ?>
                </p>
                <p class="mt-3 text-sm text-slate-600">
                    Bitte prüfen Sie vor dem Abbau, ob alle unten aufgeführten Artikel vollständig vorhanden sind, und haken Sie sie ab. Ihr Fortschritt wird automatisch gespeichert.
                </p>
            </div>

            <?php if ($totalCount === 0): ?>
                <div class="p-6 text-sm text-slate-500">
                    Für Ihre Veranstaltung wurden bislang noch keine Artikel ausgegeben. Diese Seite füllt sich automatisch, sobald die Ausgabe erfolgt ist.
                </div>
            <?php else: ?>
                <div class="flex items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-6 py-3">
                    <span class="text-sm font-medium text-slate-700" id="progress-label"><?= $confirmedCount ?> von <?= $totalCount ?> abgehakt</span>
                    <span id="save-indicator" class="text-xs text-emerald-600 opacity-0 transition-opacity">Gespeichert</span>
                </div>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($checklist->items as $entry): ?>
                        <li class="flex items-center gap-3 px-6 py-3">
                            <input
                                type="checkbox"
                                class="checklist-checkbox h-5 w-5 shrink-0 rounded border-slate-300 text-brand-600 focus:ring-brand-500"
                                data-item-id="<?= e($entry->issuedItem->id) ?>"
                                <?= $entry->issuedItem->confirmedByCustomerAt !== null ? 'checked' : '' ?>
                            >
                            <span class="flex-1 text-sm text-slate-800">
                                <?= e($entry->displayName()) ?>
                                <span class="ml-1 font-mono text-xs text-slate-400"><?= e($entry->inventoryNumber->number) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <p class="px-6 py-4 text-xs text-slate-400">
                Fehlt etwas oder haben Sie Fragen? Bitte wenden Sie sich direkt an uns.
            </p>
        </div>
    </div>

    <script>
        (function () {
            var token = <?= json_encode($token, JSON_THROW_ON_ERROR) ?>;
            var progressLabel = document.getElementById('progress-label');
            var saveIndicator = document.getElementById('save-indicator');
            var total = <?= $totalCount ?>;
            var saveIndicatorTimeout = null;

            function updateProgress() {
                var checked = document.querySelectorAll('.checklist-checkbox:checked').length;
                if (progressLabel) {
                    progressLabel.textContent = checked + ' von ' + total + ' abgehakt';
                }
            }

            function flashSaved() {
                if (!saveIndicator) { return; }
                saveIndicator.style.opacity = '1';
                clearTimeout(saveIndicatorTimeout);
                saveIndicatorTimeout = setTimeout(function () {
                    saveIndicator.style.opacity = '0';
                }, 1500);
            }

            document.querySelectorAll('.checklist-checkbox').forEach(function (checkbox) {
                checkbox.addEventListener('change', function () {
                    var itemId = checkbox.getAttribute('data-item-id');
                    var confirmed = checkbox.checked;
                    updateProgress();
                    checkbox.disabled = true;

                    fetch('/kundencheckliste/' + encodeURIComponent(token) + '/' + encodeURIComponent(itemId), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-CSRF-Token': window.CSRF_TOKEN,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: new URLSearchParams({ confirmed: confirmed ? '1' : '0' }),
                    })
                        .then(function (response) {
                            if (!response.ok) { throw new Error('request failed'); }
                            return response.json();
                        })
                        .then(function () {
                            flashSaved();
                        })
                        .catch(function () {
                            // Speichern fehlgeschlagen (z. B. Verbindung weg): Haken
                            // zurücknehmen, damit die Anzeige nicht etwas Falsches
                            // als gespeichert vorgaukelt.
                            checkbox.checked = !confirmed;
                            updateProgress();
                            window.alert('Das Speichern ist fehlgeschlagen. Bitte versuchen Sie es erneut.');
                        })
                        .finally(function () {
                            checkbox.disabled = false;
                        });
                });
            });
        })();
    </script>
</body>
</html>
