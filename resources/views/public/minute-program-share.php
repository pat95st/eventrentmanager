<?php
/** @var \App\Domain\MinuteProgram\MinuteProgramShareLink $link */
/** @var \App\Application\MinuteProgram\Query\MinuteProgramDetail $detail */
/** @var string $token */

use App\Infrastructure\Http\Csrf;
use function App\Infrastructure\Http\e;

$program = $detail->program;
$canEdit = $link->mode->canEdit();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Minutenprogramm – <?= e($program->name) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php if ($canEdit): ?>
    <script>
        window.CSRF_TOKEN = <?= json_encode(Csrf::token(), JSON_THROW_ON_ERROR) ?>;
    </script>
    <?php endif; ?>
</head>
<body class="min-h-screen bg-slate-50 antialiased">
    <div class="mx-auto max-w-xl px-4 py-10">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-6">
                <p class="text-xs font-medium uppercase tracking-wide text-brand-600">Minutenprogramm</p>
                <h1 class="mt-1 text-xl font-semibold text-slate-900"><?= e($program->name) ?></h1>
                <p class="mt-1 text-sm text-slate-500">
                    <?php if ($program->room !== null): ?><?= e($program->room) ?> · <?php endif; ?>
                    Start <?= e($program->startAt->format('d.m.Y H:i')) ?>
                </p>
                <p class="mt-1 text-sm text-slate-500" id="delay-display">
                    Aktuelle Verzögerung: <span id="delay-value"><?= e((string) $program->currentDelayMinutes) ?></span> min
                </p>
                <?php if ($canEdit): ?>
                <form id="delay-form" class="mt-3 flex items-end gap-2">
                    <div>
                        <label for="current_delay_minutes" class="block text-xs font-medium text-slate-600">Verzögerung (Minuten, auch negativ)</label>
                        <input type="number" id="current_delay_minutes" name="current_delay_minutes" value="<?= e((string) $program->currentDelayMinutes) ?>"
                               class="mt-1 w-28 rounded-md border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Übernehmen</button>
                </form>
                <?php endif; ?>
            </div>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($detail->items as $itemDetail): $item = $itemDetail->item; ?>
                    <li class="flex items-start gap-3 p-4 <?= $itemDetail->isCurrent ? 'bg-amber-50' : '' ?>">
                        <div class="w-14 shrink-0 font-mono text-sm text-slate-500"><?= e($itemDetail->currentStart->format('H:i')) ?></div>
                        <div class="flex-1">
                            <div class="text-sm font-medium text-slate-900">
                                <?= e($item->name) ?>
                                <?php if ($itemDetail->isNext): ?><span class="ml-1 rounded bg-brand-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-brand-700">Nächster</span><?php endif; ?>
                            </div>
                            <?php if ($item->description !== null): ?><div class="text-xs text-slate-500"><?= e($item->description) ?></div><?php endif; ?>
                        </div>
                        <?php if ($item->durationMinutes !== null): ?><div class="shrink-0 text-xs text-slate-400"><?= e((string) $item->durationMinutes) ?> min</div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <?php if ($canEdit): ?>
    <script>
        document.getElementById('delay-form').addEventListener('submit', function (event) {
            event.preventDefault();
            var minutes = document.getElementById('current_delay_minutes').value;
            fetch('/minutenprogramm/freigabe/<?= e($token) ?>/verzoegerung', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-Token': window.CSRF_TOKEN,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: 'current_delay_minutes=' + encodeURIComponent(minutes),
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.ok) {
                        document.getElementById('delay-value').textContent = minutes;
                        window.location.reload();
                    } else {
                        alert(data.error || 'Fehler beim Speichern.');
                    }
                });
        });
    </script>
    <?php endif; ?>
</body>
</html>
