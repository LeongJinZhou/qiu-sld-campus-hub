<?php

namespace App\Http\Controllers\Venues;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\VenueBooking;
use App\Services\Venues\VenueBookings;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VenueController extends Controller
{
    public function __construct(private readonly VenueBookings $bookings) {}

    public function index(Club $club): Response
    {
        Gate::authorize('viewInternal', $club);

        return Inertia::render('Venues/Index', [
            'club' => $club->only('slug', 'name'),
            'types' => VenueBooking::TYPES,
            'rules' => VenueBookings::LA_PLACE_RULES,
            'earliestBigRoom' => today()->addDays(VenueBookings::BIG_ROOM_LEAD_DAYS)->toDateString(),
            'bookings' => VenueBooking::where('club_id', $club->id)->latest()->limit(50)->get()->map(fn (VenueBooking $b) => [
                'id' => $b->id, 'type' => $b->type, 'summary' => $this->bookings->summary($b), 'status' => $b->status, 'sld_note' => $b->sld_note,
            ]),
        ]);
    }

    public function store(Request $request, Club $club): RedirectResponse
    {
        Gate::authorize('viewInternal', $club);
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(VenueBooking::TYPES))],
            'venue' => ['nullable', 'required_unless:type,la_place', 'string', 'max:150'],
            'date' => ['nullable', 'required_unless:type,semester', 'date'],
            'semester' => ['nullable', 'required_if:type,semester', 'string', 'max:20'],
            'weekday' => ['nullable', 'required_if:type,semester', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'purpose' => ['required', 'string', 'max:500'],
            'items' => ['nullable', 'required_if:type,la_place', 'string', 'max:500'],
            'activity' => ['required', 'string', 'max:2000'],
            'rules_accepted' => ['required_if:type,la_place', 'accepted_if:type,la_place'],
        ], ['rules_accepted.accepted_if' => 'Please agree to the La Place table rules.']);
        unset($data['rules_accepted']);

        try {
            $this->bookings->request($club, $request->user(), $data);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Request sent to SLD. You will be notified when it is confirmed.');
    }

    public function pending(): Response
    {
        return Inertia::render('Admin/Venues/Index', [
            'bookings' => VenueBooking::with('club', 'user')->orderByRaw("status = 'pending' desc")->latest()->limit(100)->get()->map(fn (VenueBooking $b) => [
                'id' => $b->id, 'club' => $b->club->name, 'by' => $b->user->name, 'type' => VenueBooking::TYPES[$b->type],
                'summary' => $this->bookings->summary($b), 'activity' => $b->activity, 'status' => $b->status, 'sld_note' => $b->sld_note,
            ]),
        ]);
    }

    public function decide(Request $request, VenueBooking $booking): RedirectResponse
    {
        $data = $request->validate(['confirm' => ['required', 'boolean'], 'note' => ['nullable', 'string', 'max:500']]);
        try {
            $this->bookings->decide($booking, $request->user(), $data['confirm'], $data['note'] ?? null);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['confirm'] ? 'Booking confirmed.' : 'Booking declined.');
    }
}
