<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['type', 'club_id', 'event_id', 'meeting_id', 'agm_id', 'session_id', 'status', 'created_by', 'submitted_at', 'decided_at'])]
class Document extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
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

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    /** @return HasOne<DocumentVersion, $this> */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(DocumentVersion::class)->latestOfMany('number');
    }

    /** @return HasMany<Approval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class)->orderBy('step_no');
    }

    /** @return HasMany<Signature, $this> */
    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->latestVersion->data ?? [];
    }

    /** @return BelongsTo<Agm, $this> */
    public function agm(): BelongsTo
    {
        return $this->belongsTo(Agm::class);
    }
}
