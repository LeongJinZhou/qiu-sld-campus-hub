<?php

namespace App\Models;

use App\Enums\StudentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_number', 'user_id', 'name', 'email', 'gender', 'mobile_no', 'programme', 'programme_code',
    'type_of_programme', 'programme_status', 'profile_status', 'year_entry', 'intake_number', 'intake_month',
    'course_duration', 'expected_end_date', 'status', 'year_group', 'synced_at',
])]
// Contact details stay server-side; forms read them explicitly when auto-filling.
#[Hidden(['mobile_no', 'email'])]
class Student extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
            'mobile_no' => 'encrypted',
            'expected_end_date' => 'date',
            'synced_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function programmeShortForm(): string
    {
        return ProgrammeAbbrev::for($this->programme_code) ?? $this->programme_code;
    }

    public function isActive(): bool
    {
        return $this->status === StudentStatus::Active;
    }
}
