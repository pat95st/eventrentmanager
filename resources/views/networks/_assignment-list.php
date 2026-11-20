<?php
/**
 * Assigned IP/MAC addresses of an inventory number (migration 155).
 *
 * @var \App\Domain\Network\NetworkInterfaceAssignment[] $assignments
 * @var array<string, \App\Domain\Network\Network> $networks by ID
 * @var bool $compact inline text for lists (default) or a table
 */

use function App\Infrastructure\Http\e;

$compact ??= true;
$addressLabel = static function (\App\Domain\Network\NetworkInterfaceAssignment $a, array $networks): string {
    $network = $a->networkId !== null ? ($networks[$a->networkId] ?? null) : null;
    $parts = [];
    foreach ([4, 6] as $version) {
        if (($address = $a->address($version)) !== null) {
            $parts[] = $network !== null ? $network->addressWithPrefix($address, $version) : $address;
        }
    }

    return implode(', ', $parts);
};
?>
<?php if ($compact): ?>
    <?php foreach ($assignments as $assignment): ?>
        <?php $addresses = $addressLabel($assignment, $networks); ?>
        <?php if ($addresses === '' && $assignment->macAddress === null) { continue; } ?>
        <span class="block font-mono text-[11px] text-slate-500 dark:text-slate-400">
            <span class="font-sans"><?= e($assignment->label) ?>:</span>
            <?= e($addresses !== '' ? $addresses : '–') ?><?php if ($assignment->macAddress !== null): ?> · MAC <?= e($assignment->macAddress) ?><?php endif; ?>
        </span>
    <?php endforeach; ?>
<?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm" data-responsive>
            <thead>
                <tr class="text-left text-xs text-slate-500 dark:text-slate-400">
                    <th class="py-1 pr-3 font-medium">Schnittstelle</th>
                    <th data-priority="2" class="py-1 pr-3 font-medium">Netzwerk</th>
                    <th data-priority="1" class="py-1 pr-3 font-medium">IP-Adresse</th>
                    <th class="py-1 font-medium">MAC-Adresse</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($assignments as $assignment): ?>
                    <?php $network = $assignment->networkId !== null ? ($networks[$assignment->networkId] ?? null) : null; ?>
                    <tr>
                        <td class="py-1.5 pr-3 text-slate-700 dark:text-slate-300"><?= e($assignment->label) ?></td>
                        <td class="py-1.5 pr-3 text-slate-600 dark:text-slate-400">
                            <?php if ($network !== null): ?>
                                <a href="/einstellungen/netzwerke/<?= e($network->id) ?>" class="hover:underline"><?= e($network->name) ?></a>
                                <?php if ($network->vlanId !== null): ?><span class="text-xs text-slate-400"> · VLAN <?= e((string) $network->vlanId) ?></span><?php endif; ?>
                            <?php else: ?>–<?php endif; ?>
                        </td>
                        <td class="py-1.5 pr-3 font-mono text-slate-700 dark:text-slate-300"><?= e($addressLabel($assignment, $networks) ?: '–') ?></td>
                        <td class="py-1.5 font-mono text-slate-700 dark:text-slate-300"><?= e($assignment->macAddress ?? '–') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
