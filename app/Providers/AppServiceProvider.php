<?php

namespace App\Providers;

use App\Enums\DocumentType;
use App\Events\DocumentApproved;
use App\Services\Events\EventApprovalActions;
use App\Services\Finance\Ledger;
use App\Services\Meetings\Meetings;
use Illuminate\Database\Eloquent\Model;
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
        // An approved fund withdrawal (07) is written to the club ledger under its event automatically.
        Event::listen(DocumentApproved::class, fn (DocumentApproved $approved) => $approved->document->type === DocumentType::FundWithdrawal
            ? app(Ledger::class)->withdrawal($approved->document) : null);

        // N+1 guard: outside production a lazy-loaded relation is reported, so missing eager loads surface in tests.
        Model::handleLazyLoadingViolationUsing(fn (Model $model, string $relation) => logger()->warning('Lazy loading', ['model' => $model::class, 'relation' => $relation]));
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
