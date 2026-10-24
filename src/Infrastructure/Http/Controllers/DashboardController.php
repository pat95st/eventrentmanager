<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;

final class DashboardController
{
    public function index(Request $request): void
    {
        Response::redirect('/artikel');
    }
}
