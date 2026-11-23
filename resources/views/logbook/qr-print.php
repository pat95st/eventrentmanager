<?php
/**
 * Printable QR code of a vehicle for the driver's logbook (migration 185),
 * sized for A6. The code is drawn in the browser (public/assets/vendor/qrcode)
 * and can be downloaded as SVG.
 *
 * @var \App\Domain\Vehicle\Vehicle $vehicle
 * @var string $url
 */

use App\Infrastructure\Http\CompanyBranding;

use function App\Infrastructure\Http\e;

$logo = CompanyBranding::logoUrl();
$file = 'Fahrtenbuch-QR-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $vehicle->name) . '.svg';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>QR-Code Fahrtenbuch – <?= e($vehicle->name) ?></title>
<style>
    @page { size: A6 portrait; margin: 8mm; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: #0f172a; background: #f1f5f9; }
    .toolbar { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; padding: 16px; }
    .toolbar button, .toolbar a { border: 1px solid #cbd5e1; background: #fff; border-radius: 8px; padding: 9px 14px; font: inherit; font-size: .9rem; color: inherit; text-decoration: none; cursor: pointer; }
    .toolbar .primary { background: #2563eb; border-color: #2563eb; color: #fff; }
    .sheet { width: 105mm; min-height: 148mm; margin: 0 auto 24px; background: #fff; padding: 9mm 8mm; display: flex; flex-direction: column; align-items: center; text-align: center; box-shadow: 0 4px 20px rgba(15,23,42,.12); }
    .sheet img.logo { max-height: 12mm; max-width: 60mm; object-fit: contain; margin-bottom: 4mm; }
    .sheet h1 { margin: 0; font-size: 15pt; }
    .sheet .plate { margin-top: 1.5mm; font-size: 12pt; font-weight: 700; letter-spacing: .06em; border: 1.5px solid #0f172a; border-radius: 1.5mm; padding: .6mm 3mm; }
    .sheet .qr { margin: 6mm 0 4mm; width: 70mm; height: 70mm; }
    .sheet .qr svg { width: 100%; height: 100%; display: block; }
    .sheet .lead { margin: 0; font-size: 12pt; font-weight: 700; }
    .sheet .steps { margin: 2mm 0 0; font-size: 9pt; color: #334155; line-height: 1.35; }
    .sheet .url { margin-top: auto; padding-top: 3mm; font-size: 6.5pt; color: #64748b; word-break: break-all; }
    @media print { body { background: #fff; } .toolbar { display: none; } .sheet { box-shadow: none; margin: 0; width: auto; min-height: 0; height: 132mm; padding: 0; } }
</style>
</head>
<body>
<div class="toolbar">
    <a href="/fahrzeuge/<?= e($vehicle->id) ?>/fahrtenbuch">&larr; Zurück</a>
    <button type="button" class="primary" onclick="window.print()">Drucken</button>
    <button type="button" data-download>Als SVG herunterladen</button>
</div>
<div class="sheet">
    <?php if ($logo !== null): ?><img class="logo" src="<?= e($logo) ?>" alt=""><?php endif; ?>
    <h1><?= e($vehicle->name) ?></h1>
    <?php if ($vehicle->licensePlate !== null): ?><div class="plate"><?= e($vehicle->licensePlate) ?></div><?php endif; ?>
    <div class="qr" data-qr data-url="<?= e($url) ?>" role="img" aria-label="QR-Code"></div>
    <p class="lead">Fahrt ins Fahrtenbuch eintragen</p>
    <p class="steps">QR-Code mit der Handykamera scannen<?= $vehicle->logbookOdometerCheck ? ', Kilometerstand ablesen' : '' ?> und die Fahrt erfassen.</p>
    <div class="url"><?= e($url) ?></div>
</div>
<script src="/assets/vendor/qrcode/qrcode.js"></script>
<script>
(function () {
    var box = document.querySelector('[data-qr]');
    var qr = qrcode(0, 'M');
    qr.addData(box.getAttribute('data-url'));
    qr.make();
    box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
    document.querySelector('[data-download]').addEventListener('click', function () {
        var svg = box.querySelector('svg').outerHTML;
        var blob = new Blob(['<?xml version="1.0" encoding="UTF-8"?>\n' + svg], { type: 'image/svg+xml' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = <?= json_encode($file, JSON_THROW_ON_ERROR) ?>;
        document.body.appendChild(a);
        a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 0);
    });
})();
</script>
</body>
</html>
