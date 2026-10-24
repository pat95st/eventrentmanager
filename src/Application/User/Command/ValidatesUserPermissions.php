<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Domain\User\Permission;

/** Gemeinsame Auflösung/Validierung der Rechte-Checkboxen für Create-/UpdateUserHandler. */
trait ValidatesUserPermissions
{
    /**
     * @param string[] $rawPermissions
     * @return array{0: string|null, 1: Permission[]} Fehlermeldung (oder null) + aufgelöste Rechte.
     */
    private function resolvePermissions(array $rawPermissions): array
    {
        $permissions = [];
        foreach ($rawPermissions as $raw) {
            $permission = Permission::tryFrom($raw);
            if ($permission === null) {
                return ['Ungültiges Recht ausgewählt.', []];
            }
            $permissions[] = $permission;
        }

        if ($permissions === []) {
            return ['Bitte mindestens ein Recht auswählen.', []];
        }

        return [null, array_values(array_unique($permissions, SORT_REGULAR))];
    }
}
