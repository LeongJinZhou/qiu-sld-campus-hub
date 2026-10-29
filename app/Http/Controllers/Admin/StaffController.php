<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApproverRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffRequest;
use App\Models\Staff;
use App\Services\Roles\StaffAccounts;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class StaffController extends Controller
{
    public function __construct(private readonly StaffAccounts $accounts) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Staff/Index', [
            'staff' => Staff::orderBy('name')->get()->map(fn (Staff $s) => [
                ...$s->only('id', 'email', 'name', 'title', 'active'),
                'approver_role' => $s->approver_role?->value,
                'signed_in' => $s->user?->last_login_at !== null,
            ]),
            'approverRoles' => array_map(fn (ApproverRole $r) => ['value' => $r->value, 'label' => $r->label()], ApproverRole::cases()),
        ]);
    }

    public function store(StaffRequest $request): RedirectResponse
    {
        $this->accounts->create($request->validated());

        return back()->with('success', 'Staff account created.');
    }

    public function update(StaffRequest $request, Staff $staff): RedirectResponse
    {
        $this->accounts->update($staff, $request->validated());

        return back()->with('success', 'Staff updated.');
    }
}
