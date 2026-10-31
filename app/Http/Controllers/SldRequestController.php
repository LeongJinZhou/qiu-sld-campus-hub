<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\SldRequest;
use App\Services\SldRequests;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** SLD sends requests to clubs; the club's committee replies within 7 working days. */
class SldRequestController extends Controller
{
    public function __construct(private readonly SldRequests $requests) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Requests/Index', [
            'clubs' => Club::orderBy('name')->get(['id', 'name'])->map(fn (Club $c) => ['value' => $c->id, 'label' => $c->name]),
            'requests' => SldRequest::with(['club', 'repliedBy'])->latest()->limit(100)->get()->map(fn (SldRequest $r) => [
                'id' => $r->id, 'club' => $r->club->name, 'subject' => $r->subject, 'body' => $r->body,
                'due_on' => $r->due_on->format('j M Y'), 'overdue' => $r->isOverdue(),
                'reply' => $r->reply, 'replied_by' => $r->repliedBy?->name, 'replied_at' => $r->replied_at?->format('j M Y'),
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'club_ids' => ['required', 'array', 'min:1'],
            'club_ids.*' => ['integer', Rule::exists('clubs', 'id')],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);
        $count = $this->requests->send($request->user(), array_map('intval', $data['club_ids']), $data['subject'], $data['body']);

        return back()->with('success', "Request sent to {$count} ".str('club')->plural($count).'.');
    }

    public function reply(Request $request, SldRequest $sldRequest): RedirectResponse
    {
        Gate::authorize('viewInternal', $sldRequest->club);
        $data = $request->validate(['reply' => ['required', 'string', 'max:5000']]);
        try {
            $this->requests->reply($sldRequest, $request->user(), $data['reply']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Reply sent to SLD.');
    }
}
