<?php

namespace App\Providers;

use App\Events\DocumentApproved;
use App\Services\Events\EventApprovalActions;
use App\Services\Meetings\Meetings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Global roles (student, staff, admin, superadmin) live under the system team; club/event
        // scoping is handled by role_assignments.
        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        Event::listen(DocumentApproved::class, [EventApprovalActions::class, 'handle']);
        Event::listen(DocumentApproved::class, [Meetings::class, 'handleApproved']);
    }
}
