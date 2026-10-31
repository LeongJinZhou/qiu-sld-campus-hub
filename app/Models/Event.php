<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\ScopeType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable([
    'club_id', 'session_id', 'name', 'summary', 'starts_at', 'ends_at', 'venue', 'mode', 'expected_participants',
    'is_outdoor', 'is_faculty_event', 'cca_participant', 'cca_committee', 'status', 'poster_path', 'poster_checklist',
    'registration_open', 'members_only', 'attendance_open', 'qr_rotate_seconds', 'approved_at', 'fee_sen', 'payment_url', 'payment_account_name', 'payment_qr_paths',
])]
#[Hidden(['attendance_secret'])]
class Event extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'draft', 'mode' => 'physical', 'is_outdoor' => false, 'is_faculty_event' => false,
        'cca_participant' => 0, 'cca_committee' => 0, 'registration_open' => false, 'attendance_open' => false,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_outdoor' => 'boolean',
            'is_faculty_event' => 'boolean',
            'registration_open' => 'boolean',
            'members_only' => 'boolean',
            'attendance_open' => 'boolean',
            'qr_rotate_seconds' => 'integer',
            'cca_participant' => 'float',
            'cca_committee' => 'float',
            'poster_checklist' => 'array',
            'payment_qr_paths' => 'array',
            'fee_sen' => 'integer',
            'status' => DocumentStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            $event->attendance_secret ??= Str::random(64);
            $event->slug ??= Str::slug($event->name).'-'.Str::lower(Str::random(6));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<Club, $this> */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** @return BelongsTo<AcademicSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'session_id');
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function committee(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'scope_id')->where('scope_type', ScopeType::Event);
    }

    /** @return HasOne<Document, $this> */
    public function proposal(): HasOne
    {
        return $this->hasOne(Document::class)->where('type', DocumentType::Proposal);
    }

    /** @return HasMany<EventRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /** Students allowed to scan tickets and attendance for this event. @return BelongsToMany<User, $this> */
    public function helpers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_helpers')->withPivot('added_by')->withTimestamps();
    }

    /** @return HasMany<Attendance, $this> */
    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<Indemnity, $this> */
    public function indemnities(): HasMany
    {
        return $this->hasMany(Indemnity::class);
    }

    /** @return HasMany<EventDocumentItem, $this> */
    public function documentItems(): HasMany
    {
        return $this->hasMany(EventDocumentItem::class);
    }

    public function posterUrl(): ?string
    {
        return $this->poster_path ? Storage::disk('public')->url($this->poster_path) : null;
    }

    /** Event length in hours, the default CCA (1 hour = 1 CCA). */
    public function durationHours(): float
    {
        return round($this->starts_at->diffInMinutes($this->ends_at, absolute: true) / 60, 1);
    }

    /** Indemnity signing closes 3 days before the event. */
    public function indemnityClosesAt(): CarbonInterface
    {
        return $this->starts_at->copy()->subDays(3);
    }
}
