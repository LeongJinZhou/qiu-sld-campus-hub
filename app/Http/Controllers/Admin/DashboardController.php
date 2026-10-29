<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\SldDashboard;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(SldDashboard $dashboard): Response
    {
        return Inertia::render('Admin/Dashboard', $dashboard->build());
    }
}
