<?php

namespace App\Providers;

use App\Enums\DocumentType;
use App\Events\DocumentApproved;
use App\Services\Events\EventApprovalActions;
use App\Services\Finance\WithdrawalProgress;
use App\Services\Meetings\Meetings;
use App\Support\SafeValueBinder;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
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
        // A fully signed fund withdrawal (07) goes to Finance; it reaches the ledger only when SLD confirms the bank-in.
        Event::listen(DocumentApproved::class, fn (DocumentApproved $approved) => $approved->document->type === DocumentType::FundWithdrawal
            ? app(WithdrawalProgress::class)->approved($approved->document) : null);

        // Every sign-in (including "remember me" coming back): committee members who owe their club fee see a pop-up.
        Event::listen(Login::class, fn () => session()->put('committee_fee_popup', true));

        // One named limiter per kind of action, each with its own counter (per person, or per IP when signed out),
        // so heavy use of one feature (e.g. the people directory) never blocks another (e.g. AI drafting).
        foreach ([
            'login' => 10,
            'public-verify' => 30,
            'likes' => 60,
            'comments' => 20,
            'reports' => 10,
            'directory' => 120,
            'uploads' => 20,
            'attend' => 30,
            'door' => 120,
            'ai' => 6,
            'live-editing' => 240,
            'nudges' => 10,
            'elections' => 30,
            'handovers' => 60,
            'reminders' => 30,
            'exports' => 20,
            'calendar-feed' => 60,
        ] as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($perMinute)->by($name.'|'.($request->user()?->id ?? $request->ip())));
        }

        // Every spreadsheet export stores user text as text (no formula injection).
        Cell::setValueBinder(new SafeValueBinder);

        // N+1 guard: outside production a lazy-loaded relation is reported, so missing eager loads surface in tests.
        Model::handleLazyLoadingViolationUsing(fn (Model $model, string $relation) => logger()->warning('Lazy loading', ['model' => $model::class, 'relation' => $relation]));
        Model::preventLazyLoading(! $this->app->isProduction());
    }
}
