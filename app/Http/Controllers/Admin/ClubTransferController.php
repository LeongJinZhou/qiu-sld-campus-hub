<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Services\Roles\ClubTransfer;
use DomainException;
use Illuminate\Http\RedirectResponse;

class ClubTransferController extends Controller
{
    public function __construct(private readonly ClubTransfer $transfers) {}

    public function store(Club $club): RedirectResponse
    {
        try {
            $this->transfers->transfer($club);
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$club->name} is now managed by its committee and advisor.");
    }

    public function destroy(Club $club): RedirectResponse
    {
        $this->transfers->takeBack($club);

        return back()->with('success', "SLD has taken back {$club->name}.");
    }
}
