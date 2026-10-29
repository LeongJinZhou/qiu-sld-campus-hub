<?php

namespace App\Http\Controllers\Membership;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Club;
use App\Models\ClubCategory;
use App\Models\Membership;
use App\Models\SavedView;
use App\Notifications\DocumentNotification;
use App\Services\Documents\MemberListExport;
use App\Services\Documents\PdfConverter;
use App\Services\Membership\MembersQuery;
use App\Services\Time\SessionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Members list: committee/advisor see their club; SLD sees every club with extra filters. */
class MembersController extends Controller
{
    public function __construct(private readonly MembersQuery $members) {}

    public function club(Request $request, Club $club, SessionResolver $sessions): Response
    {
        Gate::authorize('viewInternal', $club);

        return $this->page($request, $club, $sessions);
    }

    public function all(Request $request, SessionResolver $sessions): Response
    {
        return $this->page($request, null, $sessions);
    }

    public function export(Request $request, Club $club, string $format, MemberListExport $export, PdfConverter $pdf): BinaryFileResponse
    {
        Gate::authorize('viewInternal', $club);
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $file = $export->render($club, $this->members->rows([$club->id], $this->filters($request)));
        if ($format === 'pdf') {
            $converted = $pdf->convert($file);
            @unlink($file);
            $file = $converted;
        }

        return response()->download($file, "{$club->name} - Full Member List.{$format}")->deleteFileAfterSend();
    }

    /** Reminds members whose payment is still outstanding. */
    public function remind(Club $club): RedirectResponse
    {
        Gate::authorize('viewInternal', $club);
        $rows = $this->members->rows([$club->id], ['payment' => ['unpaid']])->whereIn('status', ['pending', 'grace']);
        $users = Membership::with('user')->whereIn('id', $rows->pluck('id'))->get()->pluck('user');
        Notification::send($users, new DocumentNotification("Complete your {$club->name} membership", 'Your membership payment is still outstanding.', route('membership.join', $club)));

        return back()->with('success', "Reminder sent to {$users->count()} member(s).");
    }

    public function saveView(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60'], 'filters' => ['required', 'array']]);
        SavedView::create(['user_id' => $request->user()->id, 'page' => 'members', ...$data]);

        return back()->with('success', 'View saved.');
    }

    public function deleteView(Request $request, SavedView $view): RedirectResponse
    {
        abort_unless($view->user_id === $request->user()->id, 403);
        $view->delete();

        return back();
    }

    private function page(Request $request, ?Club $club, SessionResolver $sessions): Response
    {
        $filters = $this->filters($request);
        $rows = $this->members->rows($club ? [$club->id] : null, $filters);
        $all = $this->members->rows($club ? [$club->id] : null);

        return Inertia::render('Membership/Members', [
            'club' => $club?->only('slug', 'name'),
            'filters' => (object) $filters, // always a JSON object, even with no filters
            'counts' => $this->members->counts($rows),
            'rows' => $rows->take(500)->values(),
            'truncated' => $rows->count() > 500,
            'options' => [
                'positions' => $all->pluck('position')->unique()->sort()->values(),
                'year_groups' => $all->pluck('year_group')->filter()->unique()->sort()->values(),
                'programmes' => $all->pluck('programme_short')->filter()->unique()->sort()->values(),
                'intakes' => $all->pluck('intake')->filter()->unique()->sortDesc()->values(),
                'collectors' => $all->pluck('collected_by')->filter()->unique()->sort()->values(),
                'sessions' => [$sessions->current()->name, ...AcademicSession::orderByDesc('starts_on')->pluck('name')->reject(fn ($n) => $n === $sessions->current()->name)],
                'clubs' => $club ? [] : Club::orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
                'categories' => $club ? [] : ClubCategory::orderBy('name')->pluck('name'),
            ],
            'views' => SavedView::where('user_id', $request->user()->id)->where('page', 'members')->get(['id', 'name', 'filters']),
        ]);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $f = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'session' => ['nullable', 'regex:/^\d{4}\/\d{4}$/'],
            'status' => ['nullable', 'array'], 'position' => ['nullable', 'array'], 'payment' => ['nullable', 'array'],
            'method' => ['nullable', 'array'], 'collected_by' => ['nullable', 'array'], 'year_group' => ['nullable', 'array'],
            'programme_short' => ['nullable', 'array'], 'intake' => ['nullable', 'array'], 'club_id' => ['nullable', 'array'],
            'category' => ['nullable', 'array'], 'club_status' => ['nullable', 'array'],
            'joined_from' => ['nullable', 'date'], 'joined_to' => ['nullable', 'date'],
            'expiring_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'min_events' => ['nullable', 'integer', 'min:0'], 'min_cca' => ['nullable', 'numeric', 'min:0'],
            'sort' => ['nullable', 'array', 'max:3'], 'sort.*' => ['array', 'size:2'],
            'sort.*.0' => ['string', 'in:'.implode(',', MembersQuery::SORTABLE)], 'sort.*.1' => ['in:asc,desc'],
        ]);

        return array_filter($f, fn ($v) => $v !== null && $v !== [] && $v !== '');
    }
}
