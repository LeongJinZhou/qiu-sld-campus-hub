<?php

namespace App\Http\Controllers;

use App\Models\ClubApplication;
use App\Models\ClubCategory;
use App\Models\Student;
use App\Services\Clubs\ClubApplications;
use App\Services\Roles\CompulsoryPositionsCheck;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Start a new club: apply, advisor signs, SLD approves. */
class ClubApplicationController extends Controller
{
    public function __construct(private readonly ClubApplications $applications) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Clubs/Apply', [
            'categories' => ClubCategory::orderBy('name')->get(['id', 'name']),
            'advisors' => $this->applications->advisors(),
            'positions' => CompulsoryPositionsCheck::POSITIONS,
            'me' => $request->user()->student?->only('student_number', 'name'),
            'mine' => ClubApplication::where('applicant_id', $request->user()->id)->latest()->get()->map(fn (ClubApplication $a) => ['id' => $a->id, 'name' => $a->data['name'], 'status' => ClubApplication::STATUSES[$a->status]]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        try {
            $application = $this->applications->submit($request->user(), $data, $data['signature'], $data['signature_strokes']);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return to_route('club-applications.show', $application)->with('success', 'Application signed and sent to your proposed advisor.');
    }

    public function show(Request $request, ClubApplication $application): Response
    {
        $user = $request->user();
        abort_unless(in_array($user->id, [$application->applicant_id, $application->advisor_id], true) || $user->isSldAdmin(), 403);
        $officers = collect($application->data['officers'])->map(fn (string $n, string $p) => ['position' => $p, 'student_number' => $n, 'name' => Student::where('student_number', $n)->value('name')])->values();

        return Inertia::render('Clubs/Application', [
            'application' => [
                'id' => $application->id, 'status' => $application->status, 'status_label' => ClubApplication::STATUSES[$application->status],
                'data' => $application->data, 'officers' => $officers, 'note' => $application->note,
                'category' => ClubCategory::find($application->data['category_id'] ?? null)?->name,
                'applicant' => $application->applicant->name, 'advisor' => $application->advisor->name, 'advisor_id' => $application->advisor_id,
                'advisor_signed_at' => $application->advisor_signed_at?->format('D, j M Y'), 'created_at' => $application->created_at?->format('D, j M Y'),
                'club_slug' => $application->club?->slug,
            ],
            'can' => [
                'sign' => $application->status === 'advisor' && $user->id === $application->advisor_id,
                'decide' => $application->status === 'sld' && $user->isSldAdmin(),
                'resubmit' => $application->status === 'returned' && $user->id === $application->applicant_id,
            ],
            'categories' => ClubCategory::orderBy('name')->get(['id', 'name']),
            'advisors' => $this->applications->advisors(),
            'positions' => CompulsoryPositionsCheck::POSITIONS,
        ]);
    }

    public function sign(Request $request, ClubApplication $application): RedirectResponse
    {
        $data = $request->validate(['signature' => ['required', 'string'], 'signature_strokes' => ['required', 'string', 'max:307200']]);

        return $this->attempt(fn () => $this->applications->advisorSign($application, $request->user(), $data['signature'], $data['signature_strokes']), 'Signed. SLD reviews it next.');
    }

    public function sendBack(Request $request, ClubApplication $application): RedirectResponse
    {
        $note = $request->validate(['note' => ['required', 'string', 'max:1000']])['note'];

        return $this->attempt(fn () => $this->applications->returnToApplicant($application, $request->user(), $note), 'Returned to the applicant.');
    }

    public function decide(Request $request, ClubApplication $application): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'note' => ['nullable', 'required_if:approve,false', 'string', 'max:1000']]);

        return $this->attempt(fn () => $this->applications->decide($application, $request->user(), $data['approve'], $data['note'] ?? null), $data['approve'] ? 'Approved. The club is now open.' : 'Rejected.');
    }

    public function resubmit(Request $request, ClubApplication $application): RedirectResponse
    {
        abort_unless($application->status === 'returned' && $application->applicant_id === $request->user()->id, 403);
        $data = $this->validated($request);

        return $this->attempt(fn () => $this->applications->submit($request->user(), $data, $data['signature'], $data['signature_strokes'], $application), 'Corrected, signed and sent to your advisor again.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:club_categories,id'],
            'bio' => ['required', 'string', 'max:1000'],
            'objectives' => ['required', 'string', 'max:3000'],
            'officers' => ['required', 'array'],
            'officers.*' => ['required', 'string', 'max:30'],
            'advisor_id' => ['required', 'integer'],
            'signature' => ['required', 'string'],
            'signature_strokes' => ['required', 'string', 'max:307200'],
        ]);
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
