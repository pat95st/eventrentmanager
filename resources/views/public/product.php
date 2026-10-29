<?php
/** @var \App\Application\Article\Query\ArticleDetail $detail */

use function App\Infrastructure\Http\e;

$article = $detail->article;
$hasImage = $article->images !== [];
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($article->name) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="min-h-screen bg-slate-50 antialiased">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white">
            <?php if ($hasImage): ?>
                <img src="/produkte/<?= e($article->id) ?>/bild" alt="<?= e($article->name) ?>" class="h-72 w-full object-cover">
            <?php endif; ?>
            <div class="p-6">
                <h1 class="text-xl font-semibold text-slate-900"><?= e($article->name) ?></h1>
                <?php if ($detail->manufacturer !== null): ?><p class="mt-1 text-sm text-slate-500"><?= e($detail->manufacturer->name) ?></p><?php endif; ?>

                <?php if ($article->isBillable && $article->dailyRentalPrice !== null): ?>
                    <p class="mt-4 text-lg font-semibold text-slate-900"><?= e($article->dailyRentalPrice) ?> € <span class="text-sm font-normal text-slate-500">/ Einsatztag</span></p>
                <?php endif; ?>

                <?php if ($article->description !== null && $article->description !== ''): ?>
                    <p class="mt-4 whitespace-pre-line text-sm text-slate-700"><?= e($article->description) ?></p>
                <?php endif; ?>

                <p class="mt-6 text-xs text-slate-400">Diese Seite dient als Produktlink für externe Vertriebskanäle. Für ein Angebot wenden Sie sich bitte direkt an uns.</p>
            </div>
        </div>
    </div>
</body>
</html>
