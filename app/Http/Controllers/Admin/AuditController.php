<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Audit/Index', [
            'entries' => AuditLog::with('actor:id,name')->latest('id')->paginate(50)->through(fn (AuditLog $e) => [
                'id' => $e->id,
                'at' => $e->created_at?->toDateTimeString(),
                'actor' => $e->actor?->name ?? 'System',
                'action' => $e->action,
                'subject' => $e->subject_type ? "{$e->subject_type} #{$e->subject_id}" : null,
                'data' => $e->data,
            ]),
        ]);
    }
}
