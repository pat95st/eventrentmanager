<?php
/** @var \App\Application\MinuteProgram\Query\MinuteProgramDetail $detail */
/** @var string $token */

use function App\Infrastructure\Http\e;

$program = $detail->program;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($program->name) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>
        body { background: #0f172a; }
    </style>
</head>
<body class="flex min-h-screen flex-col items-center justify-center text-white">
    <p id="program-name" class="text-2xl font-medium text-slate-300"><?= e($program->name) ?></p>
    <p id="next-label" class="mt-4 text-4xl font-semibold"></p>
    <p class="mt-2 text-lg text-slate-400">Nächster Programmpunkt startet um</p>
    <p id="next-at" class="text-6xl font-bold tabular-nums"></p>
    <p id="finished-label" class="hidden text-3xl font-semibold text-emerald-400">Programm beendet</p>

    <script>
        var token = <?= json_encode($token, JSON_THROW_ON_ERROR) ?>;

        function refresh() {
            fetch('/minutenprogramm/freigabe/' + token + '/countdown.json')
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.error) {
                        return;
                    }
                    document.getElementById('program-name').textContent = data.programName + (data.room ? ' · ' + data.room : '');
                    if (data.finished) {
                        document.getElementById('next-label').textContent = '';
                        document.getElementById('next-at').textContent = '';
                        document.getElementById('finished-label').classList.remove('hidden');

                        return;
                    }
                    document.getElementById('finished-label').classList.add('hidden');
                    document.getElementById('next-label').textContent = data.nextLabel || '';
                    if (data.nextAt) {
                        var date = new Date(data.nextAt);
                        document.getElementById('next-at').textContent = date.toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
                    }
                });
        }

        refresh();
        setInterval(refresh, 15000);
    </script>
</body>
</html>
