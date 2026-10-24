<?php
/** @var string $html */
?>

<div class="mx-auto max-w-3xl">
    <h1 class="mb-6 text-xl font-semibold text-slate-900 dark:text-slate-100">Datenschutzerklärung</h1>

    <?php if (trim($html) === ''): ?>
        <p class="text-sm text-slate-500 dark:text-slate-400">Es wurden noch keine Angaben hinterlegt.</p>
    <?php else: ?>
        <div class="max-w-none text-sm leading-relaxed text-slate-700 dark:text-slate-300
                    [&_h1]:mb-3 [&_h1]:mt-6 [&_h1]:text-lg [&_h1]:font-semibold [&_h1]:text-slate-900 dark:[&_h1]:text-slate-100
                    [&_h2]:mb-2 [&_h2]:mt-5 [&_h2]:text-base [&_h2]:font-semibold [&_h2]:text-slate-900 dark:[&_h2]:text-slate-100
                    [&_h3]:mb-2 [&_h3]:mt-4 [&_h3]:text-sm [&_h3]:font-semibold [&_h3]:text-slate-900 dark:[&_h3]:text-slate-100
                    [&_p]:mb-3
                    [&_ul]:mb-3 [&_ul]:list-disc [&_ul]:pl-5
                    [&_ol]:mb-3 [&_ol]:list-decimal [&_ol]:pl-5
                    [&_li]:mb-1
                    [&_a]:text-brand-600 [&_a]:underline dark:[&_a]:text-brand-400
                    [&_strong]:font-semibold">
            <?= $html ?>
        </div>
    <?php endif; ?>
</div>
