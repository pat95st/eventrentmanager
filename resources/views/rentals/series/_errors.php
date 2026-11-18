<?php
/** @var array<string, string> $errors */

use function App\Infrastructure\Http\e;

?>
<?php if (($errors ?? []) !== []): ?>
    <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300">
        <ul class="list-inside list-disc space-y-0.5">
            <?php foreach (array_unique($errors) as $message): ?>
                <li><?= e($message) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
