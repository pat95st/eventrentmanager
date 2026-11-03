<?php
/** @var \App\Domain\InvoiceCorrection\InvoiceCorrection $correction */

use function App\Infrastructure\Http\e;

if ($correction->isDraft()) {
    $badge = ['Entwurf', 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'];
} elseif ($correction->awaitsSettlement()) {
    $badge = [$correction->grossTotal() < 0 ? 'Erstattung offen' : 'Nachzahlung offen', 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400'];
} else {
    $badge = [$correction->settledAt !== null ? ($correction->grossTotal() < 0 ? 'Erstattet' : 'Bezahlt') : 'Ausgestellt', 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-400'];
}
?>
<span class="rounded-full px-2 py-0.5 text-xs font-medium <?= $badge[1] ?>"><?= e($badge[0]) ?></span>
