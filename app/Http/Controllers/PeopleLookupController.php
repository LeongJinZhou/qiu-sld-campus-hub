<?php

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Type-ahead for committee forms: a name, QIU email or student ID gives up to 8 active students with their
 * name, email and student ID, so nobody retypes them. Only people who run something (any committee, advisor
 * or event role) and SLD may look people up.
 */
class PeopleLookupController extends Controller
{
    public const LIMIT = 8;

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isSldAdmin() || $user->roleAssignments()->exists(), 403);
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q']);
        $words = array_slice(preg_split('/\s+/', $q) ?: [], 0, 4);

        $students = Student::query()->where('status', StudentStatus::Active)
            ->where(function (Builder $b) use ($q, $words): void {
                $b->where('student_number', 'like', $this->like($q))->orWhere('email', 'like', $this->like($q))
                    ->orWhere(fn (Builder $n) => array_map(fn (string $w) => $n->where('name', 'like', $this->like($w)), $words));
            })
            ->orderByRaw('CASE WHEN student_number = ? OR email = ? THEN 0 ELSE 1 END', [$q, $q])->orderBy('name')
            ->limit(self::LIMIT)->get();

        return response()->json($students->map(fn (Student $s) => [
            'student_number' => $s->student_number,
            'name' => $s->name,
            'email' => $s->email,
            'programme' => $s->programmeShortForm(),
        ])->values());
    }

    private function like(string $value): string
    {
        return '%'.addcslashes($value, '%_\\').'%';
    }
}
