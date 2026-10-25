<?php
/**
 * Visueller PDF-Vorlagen-Editor (siehe PdfLayoutSettingsController,
 * App\Domain\Settings\PdfLayout\PdfLayoutBlueprint). Zeigt eine
 * maßstäblich verkleinerte A4-Seite (1mm = 2.4px) mit einer frei
 * verschiebbaren Box je Block dieses Dokumenttyps. Gespeichert wird per
 * fetch() als ein Formular-kodierter Block "positions[<key>][x]=…"
 * (identisches Encoding wie ein normales <form>, siehe
 * resources/views/customers/_quick_create_modal.php für das etablierte
 * Muster AJAX+CSRF-Header in diesem Projekt), nicht als JSON — Request
 * kennt bislang keinen JSON-Body-Parser, siehe dortiger Klassendoc.
 *
 * @var \App\Domain\Settings\PdfLayout\PdfDocumentType $documentType
 * @var array<string, \App\Domain\Settings\PdfLayout\PdfLayoutElementDefinition> $elements
 * @var \App\Domain\Settings\PdfLayout\PdfLayoutSettings $settings
 */

use App\Domain\Settings\PdfLayout\PdfDocumentType;

use function App\Infrastructure\Http\e;
use function App\Infrastructure\Http\t;

// 1mm = 2.4px -> A4 (210 x 297mm) wird als ca. 504 x 713px große Seite
// dargestellt, groß genug zum präzisen Ziehen, aber noch ohne Scrollen
// in der Breite auf einem normalen Laptop-Bildschirm sichtbar.
$pxPerMm = 2.4;
$pageWidthMm = 210.0;
$pageHeightMm = 297.0;

// Fensterumschlag/Falzmarken (DIN 5008) zeichnen aktuell nur Rechnung,
// Angebot und Auftragsbestätigung tatsächlich (siehe *PdfGenerator) — die
// Mahnung hat zwar auch eine Kundenadresse, aber (bewusst, siehe
// DunningPdfGenerator-Klassendoc) kein Fensterkuvert-Layout. Die
// Orientierungshilfe im Editor soll das nicht vorspiegeln.
$hasWindowZone = in_array($documentType, [PdfDocumentType::Invoice, PdfDocumentType::Offer, PdfDocumentType::OrderConfirmation], true);

$boxes = [];
foreach ($elements as $key => $definition) {
    $position = $settings->get($key);
    $boxes[$key] = [
        'label' => $definition->label,
        'xMm' => $position?->xMm ?? $definition->defaultXMm,
        'yMm' => $position?->yMm ?? $definition->defaultYMm,
        'widthMm' => $position?->widthMm ?? $definition->defaultWidthMm,
        'fontSize' => $position?->fontSize ?? $definition->defaultFontSize,
        'supportsFontSize' => $definition->supportsFontSize,
        'customized' => $position !== null,
    ];
}
?>
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 dark:text-slate-100"><?= e(t('settings.pdf_layout.title')) ?></h1>
        <p class="max-w-2xl text-sm text-slate-500 dark:text-slate-400"><?= e(t('settings.pdf_layout.subtitle')) ?></p>
    </div>
    <div class="flex flex-wrap gap-1 rounded-md border border-slate-200 bg-white p-1 dark:border-slate-800 dark:bg-slate-900">
        <?php foreach (PdfDocumentType::cases() as $case): ?>
            <a
                href="/einstellungen/pdf-layout?typ=<?= e($case->value) ?>"
                class="rounded px-3 py-1.5 text-sm font-medium transition-colors <?= $case === $documentType
                    ? 'bg-brand-600 text-white'
                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' ?>"
            ><?= e($case->label()) ?></a>
        <?php endforeach; ?>
    </div>
</div>

<div id="pdf-layout-flash" class="mb-4 hidden rounded-md px-4 py-2 text-sm"></div>

<div class="flex flex-wrap items-start gap-6">
    <div
        id="pdf-layout-page"
        class="relative shrink-0 border border-slate-300 bg-white shadow-sm dark:border-slate-700"
        style="width: <?= $pageWidthMm * $pxPerMm ?>px; height: <?= $pageHeightMm * $pxPerMm ?>px;"
        data-px-per-mm="<?= $pxPerMm ?>"
        data-page-width-mm="<?= $pageWidthMm ?>"
        data-page-height-mm="<?= $pageHeightMm ?>"
    >
        <?php if ($hasWindowZone): ?>
            <!-- Sichtfenster des Fensterumschlags (DIN 5008), nur Orientierungshilfe, wird nie mitgespeichert. -->
            <div
                class="pointer-events-none absolute border border-dashed border-amber-400/70 bg-[repeating-linear-gradient(45deg,rgba(251,191,36,0.12)_0,rgba(251,191,36,0.12)_6px,transparent_6px,transparent_12px)]"
                style="left: <?= 20.0 * $pxPerMm ?>px; top: <?= 45.0 * $pxPerMm ?>px; width: <?= 85.0 * $pxPerMm ?>px; height: <?= (90.0 - 45.0) * $pxPerMm ?>px;"
                title="<?= e(t('settings.pdf_layout.window_zone_hint')) ?>"
            ></div>
            <!-- Falzmarken (DIN 5008), am linken Seitenrand. -->
            <div class="pointer-events-none absolute left-0 h-px w-3 bg-slate-400" style="top: <?= 105.0 * $pxPerMm ?>px;"></div>
            <div class="pointer-events-none absolute left-0 h-px w-3 bg-slate-400" style="top: <?= 210.0 * $pxPerMm ?>px;"></div>
        <?php endif; ?>

        <?php foreach ($boxes as $key => $box): ?>
            <div
                data-pdf-layout-box
                data-key="<?= e($key) ?>"
                data-font-size="<?= $box['fontSize'] !== null ? e((string) $box['fontSize']) : '' ?>"
                data-width-mm="<?= $box['widthMm'] !== null ? e((string) $box['widthMm']) : '' ?>"
                class="absolute cursor-move select-none rounded border-2 <?= $box['customized'] ? 'border-brand-500 bg-brand-50/80 dark:bg-brand-500/10' : 'border-slate-300 bg-slate-50/80 dark:border-slate-600 dark:bg-slate-800/60' ?> px-2 py-1 text-[11px] font-medium text-slate-700 shadow-sm dark:text-slate-200"
                style="left: <?= $box['xMm'] * $pxPerMm ?>px; top: <?= $box['yMm'] * $pxPerMm ?>px; <?= $box['widthMm'] !== null ? 'width: ' . ($box['widthMm'] * $pxPerMm) . 'px;' : '' ?>"
            ><?= e($box['label']) ?></div>
        <?php endforeach; ?>
    </div>

    <div class="min-w-[220px] flex-1 space-y-4">
        <div id="pdf-layout-fields" class="space-y-3">
            <?php foreach ($boxes as $key => $box): ?>
                <div data-pdf-layout-field data-key="<?= e($key) ?>" class="rounded-md border border-slate-200 bg-white p-3 text-xs dark:border-slate-800 dark:bg-slate-900">
                    <div class="mb-1.5 flex items-center justify-between gap-2">
                        <span class="font-semibold text-slate-700 dark:text-slate-300"><?= e($box['label']) ?></span>
                        <button type="button" data-reset-element class="text-slate-400 hover:text-red-600 dark:hover:text-red-400"><?= e(t('settings.pdf_layout.reset_element')) ?></button>
                    </div>
                    <div class="flex flex-wrap items-center gap-3 text-slate-500 dark:text-slate-400">
                        <span>X: <span data-display="x"><?= number_format($box['xMm'], 1) ?></span>mm</span>
                        <span>Y: <span data-display="y"><?= number_format($box['yMm'], 1) ?></span>mm</span>
                        <?php if ($box['widthMm'] !== null): ?>
                            <label class="flex items-center gap-1">
                                <?= e(t('settings.pdf_layout.width_label')) ?>
                                <input type="number" step="1" min="10" max="190" data-width-input value="<?= number_format($box['widthMm'], 0) ?>" class="w-16 rounded border border-slate-300 px-1 py-0.5 dark:border-slate-700 dark:bg-slate-950">
                            </label>
                        <?php endif; ?>
                        <?php if ($box['supportsFontSize']): ?>
                            <label class="flex items-center gap-1">
                                <?= e(t('settings.pdf_layout.font_size_label')) ?>
                                <input type="number" step="0.5" min="4" max="72" data-font-size-input value="<?= $box['fontSize'] !== null ? number_format($box['fontSize'], 1) : '' ?>" class="w-16 rounded border border-slate-300 px-1 py-0.5 dark:border-slate-700 dark:bg-slate-950">
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="flex gap-2">
            <button type="button" id="pdf-layout-save" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"><?= e(t('settings.pdf_layout.save')) ?></button>
            <button type="button" id="pdf-layout-reset-all" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"><?= e(t('settings.pdf_layout.reset_all')) ?></button>
        </div>
    </div>
</div>

<script>
(function () {
    var page = document.getElementById('pdf-layout-page');
    var pxPerMm = parseFloat(page.dataset.pxPerMm);
    var pageWidthMm = parseFloat(page.dataset.pageWidthMm);
    var pageHeightMm = parseFloat(page.dataset.pageHeightMm);
    var documentType = <?= json_encode($documentType->value) ?>;
    var saveUrl = '/einstellungen/pdf-layout?typ=' + encodeURIComponent(documentType);

    function fieldFor(key) {
        return document.querySelector('[data-pdf-layout-field][data-key="' + key + '"]');
    }

    function clampMm(valueMm, maxMm) {
        return Math.max(0, Math.min(maxMm, valueMm));
    }

    function updateDisplays(box) {
        var key = box.dataset.key;
        var field = fieldFor(key);
        if (!field) {
            return;
        }
        var xMm = parseFloat(box.style.left) / pxPerMm;
        var yMm = parseFloat(box.style.top) / pxPerMm;
        var xDisplay = field.querySelector('[data-display="x"]');
        var yDisplay = field.querySelector('[data-display="y"]');
        if (xDisplay) {
            xDisplay.textContent = xMm.toFixed(1);
        }
        if (yDisplay) {
            yDisplay.textContent = yMm.toFixed(1);
        }
    }

    // Drag-and-drop je Box, per Maus (Touch wird hier bewusst nicht
    // unterstützt — Feinpositionierung per Finger ist unpraktikabel,
    // die Zahlenfelder rechts bleiben die Alternative für Tablet/Touch).
    document.querySelectorAll('[data-pdf-layout-box]').forEach(function (box) {
        var dragging = false;
        var startMouseX = 0;
        var startMouseY = 0;
        var startLeft = 0;
        var startTop = 0;

        box.addEventListener('mousedown', function (event) {
            dragging = true;
            startMouseX = event.clientX;
            startMouseY = event.clientY;
            startLeft = parseFloat(box.style.left) || 0;
            startTop = parseFloat(box.style.top) || 0;
            box.classList.add('z-10', 'opacity-80');
            event.preventDefault();
        });

        document.addEventListener('mousemove', function (event) {
            if (!dragging) {
                return;
            }
            var newLeft = startLeft + (event.clientX - startMouseX);
            var newTop = startTop + (event.clientY - startMouseY);
            newLeft = clampMm(newLeft / pxPerMm, pageWidthMm - 5) * pxPerMm;
            newTop = clampMm(newTop / pxPerMm, pageHeightMm - 5) * pxPerMm;
            box.style.left = newLeft + 'px';
            box.style.top = newTop + 'px';
            updateDisplays(box);
        });

        document.addEventListener('mouseup', function () {
            if (dragging) {
                dragging = false;
                box.classList.remove('z-10', 'opacity-80');
            }
        });
    });

    document.querySelectorAll('[data-width-input]').forEach(function (input) {
        input.addEventListener('input', function () {
            var key = input.closest('[data-pdf-layout-field]').dataset.key;
            var box = document.querySelector('[data-pdf-layout-box][data-key="' + key + '"]');
            if (box && input.value !== '') {
                box.style.width = (parseFloat(input.value) * pxPerMm) + 'px';
                box.dataset.widthMm = input.value;
            }
        });
    });

    document.querySelectorAll('[data-font-size-input]').forEach(function (input) {
        input.addEventListener('input', function () {
            var key = input.closest('[data-pdf-layout-field]').dataset.key;
            var box = document.querySelector('[data-pdf-layout-box][data-key="' + key + '"]');
            if (box) {
                box.dataset.fontSize = input.value;
            }
        });
    });

    document.querySelectorAll('[data-reset-element]').forEach(function (button) {
        button.addEventListener('click', function () {
            var field = button.closest('[data-pdf-layout-field]');
            var key = field.dataset.key;
            var box = document.querySelector('[data-pdf-layout-box][data-key="' + key + '"]');
            if (box) {
                box.remove();
            }
            field.remove();
        });
    });

    function showFlash(message, isError) {
        var flash = document.getElementById('pdf-layout-flash');
        flash.textContent = message;
        flash.className = 'mb-4 rounded-md px-4 py-2 text-sm ' + (isError
            ? 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
            : 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300');
    }

    function collectPositions() {
        var positions = {};
        document.querySelectorAll('[data-pdf-layout-box]').forEach(function (box) {
            var key = box.dataset.key;
            var xMm = parseFloat(box.style.left) / pxPerMm;
            var yMm = parseFloat(box.style.top) / pxPerMm;
            var widthMm = box.dataset.widthMm !== '' && box.dataset.widthMm !== undefined ? box.dataset.widthMm : null;
            var fontSize = box.dataset.fontSize !== '' && box.dataset.fontSize !== undefined ? box.dataset.fontSize : null;
            positions[key] = { x: xMm, y: yMm, width: widthMm, fontSize: fontSize };
        });

        return positions;
    }

    function save(positions) {
        var body = new URLSearchParams();
        Object.keys(positions).forEach(function (key) {
            var position = positions[key];
            body.append('positions[' + key + '][x]', position.x);
            body.append('positions[' + key + '][y]', position.y);
            if (position.width !== null) {
                body.append('positions[' + key + '][width]', position.width);
            }
            if (position.fontSize !== null) {
                body.append('positions[' + key + '][fontSize]', position.fontSize);
            }
        });

        return fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': window.CSRF_TOKEN,
            },
            body: body.toString(),
        });
    }

    document.getElementById('pdf-layout-save').addEventListener('click', function () {
        save(collectPositions()).then(function (response) {
            if (response.ok) {
                showFlash(<?= json_encode(t('settings.pdf_layout.saved')) ?>, false);
            } else {
                showFlash(<?= json_encode(t('settings.pdf_layout.error_generic')) ?>, true);
            }
        }).catch(function () {
            showFlash(<?= json_encode(t('settings.pdf_layout.error_generic')) ?>, true);
        });
    });

    document.getElementById('pdf-layout-reset-all').addEventListener('click', function () {
        save({}).then(function () {
            window.location.reload();
        });
    });
})();
</script>
