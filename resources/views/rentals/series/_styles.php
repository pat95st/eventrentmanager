<?php
// Shared CSS classes of the series pages.
$card = 'rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900';
$input = 'mt-1 w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm dark:border-slate-700 dark:bg-slate-900';
$label = 'block text-xs font-medium text-slate-600 dark:text-slate-400';
$primaryButton = 'rounded-md bg-brand-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-brand-700';
$secondaryButton = 'inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800';
$csrf = '<input type="hidden" name="csrf_token" value="' . \App\Infrastructure\Http\e(\App\Infrastructure\Http\Csrf::token()) . '">';
