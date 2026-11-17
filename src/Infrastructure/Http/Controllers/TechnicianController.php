<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Application\Query\QueryBus;
use App\Application\Technician\Query\ListTechnicianRentals;
use App\Domain\Technician\TechnicianProfileRepositoryInterface;
use App\Domain\User\Permission;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Http\Auth\CurrentUser;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\View;

/**
 * Read-only technician page (/techniker/{id}) for planners who assign
 * technicians to events. The user form under /benutzer stays reserved for
 * administrators; this page only shows contact details and assignments.
 */
final class TechnicianController
{
    public function __construct(
        private readonly QueryBus $queries,
        private readonly UserRepositoryInterface $users,
        private readonly TechnicianProfileRepositoryInterface $profiles,
    ) {
    }

    public function show(Request $request): void
    {
        $technician = $this->users->findById($request->param('id'));
        if ($technician === null || !in_array(Permission::Techniker, $technician->permissions, true)) {
            Response::notFound('Der Techniker wurde nicht gefunden.');

            return;
        }

        echo View::page('technicians/show', [
            'technician' => $technician,
            'profile' => $this->profiles->find($technician->id),
            'rows' => $this->queries->ask(new ListTechnicianRentals($technician->id)),
            'canEditUser' => CurrentUser::get()?->isAdministrator() ?? false,
        ], activeNav: 'rentals', title: $technician->displayName());
    }
}
