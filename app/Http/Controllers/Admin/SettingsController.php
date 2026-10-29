<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\AcademicSession;
use App\Models\IntakeMap;
use App\Models\ProgrammeAbbrev;
use App\Models\Setting;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\Camsys\StatusMapper;
use App\Services\Time\SessionResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(SessionResolver $sessions): Response
    {
        return Inertia::render('Admin/Settings/Index', [
            'programmes' => ProgrammeAbbrev::orderBy('programme_code')->get(['programme_code', 'abbreviation']),
            // Programme codes in camsys that have no short form yet.
            'unmappedCodes' => Student::whereNotIn('programme_code', ProgrammeAbbrev::select('programme_code'))->distinct()->pluck('programme_code'),
            'intakes' => IntakeMap::orderBy('intake_number')->get(['intake_number', 'month']),
            'statuses' => Setting::read(StatusMapper::SETTING_KEY, StatusMapper::DEFAULTS),
            'sessions' => AcademicSession::orderByDesc('starts_on')->get()->map(fn (AcademicSession $s) => [
                'name' => $s->name, 'starts_on' => $s->starts_on->toDateString(), 'ends_on' => $s->ends_on->toDateString(),
            ]),
            'currentSession' => $sessions->current()->name,
        ]);
    }

    public function update(SettingsRequest $request, string $section, AuditLogger $audit, SessionResolver $sessions): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($section, $data, $sessions): void {
            match ($section) {
                'programmes' => $this->replace(ProgrammeAbbrev::class, $data['items']),
                'intakes' => $this->replace(IntakeMap::class, $data['items']),
                'statuses' => Setting::write(StatusMapper::SETTING_KEY, ['active' => $data['active'], 'alumni' => $data['alumni']]),
                'sessions' => $sessions->forName($data['name']),
            };
        });
        $audit->log("settings.{$section}", null, $data);

        return back()->with('success', 'Settings saved. Changes apply from the next camsys sync.');
    }

    /**
     * @param  class-string<ProgrammeAbbrev|IntakeMap>  $model
     * @param  list<array<string, mixed>>  $items
     */
    private function replace(string $model, array $items): void
    {
        $model::query()->delete();
        foreach ($items as $item) {
            $model::create($item);
        }
    }
}
