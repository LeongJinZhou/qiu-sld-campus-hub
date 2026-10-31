<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'google_id', 'avatar_url', 'avatar_path', 'cover_path', 'personal_email', 'hidden_sections', 'notification_mutes', 'last_login_at'])]
#[Hidden(['remember_token', 'google_id', 'google_token', 'google_refresh_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** Social notifications a person may mute. Things that need action (payments, approvals) cannot be muted. */
    public const MUTABLE_NOTIFICATIONS = ['likes' => 'Likes', 'comments' => 'Comments', 'saves' => 'Saves', 'follows' => 'New followers'];

    public function hasMuted(string $category): bool
    {
        return in_array($category, $this->notification_mutes ?? [], true);
    }

    /** Profile sections a student may hide from their public profile. */
    public const HIDEABLE_SECTIONS = ['clubs', 'positions', 'events', 'cca'];

    protected function casts(): array
    {
        return [
            'hidden_sections' => 'array',
            'notification_mutes' => 'array',
            'last_login_at' => 'datetime',
            'google_token' => 'encrypted',
            'google_refresh_token' => 'encrypted',
            'google_token_expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (User $user) => $user->qr_token ??= Str::random(32));
    }

    /** @return HasOne<Student, $this> */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /** @return HasOne<Staff, $this> */
    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class);
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class);
    }

    /** @return BelongsToMany<Club, $this> */
    public function followedClubs(): BelongsToMany
    {
        return $this->belongsToMany(Club::class, 'club_follows');
    }

    /** @return BelongsToMany<Post, $this> */
    public function savedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'saves')->withPivot('created_at');
    }

    /** @return HasOne<EmergencyContact, $this> */
    public function emergencyContact(): HasOne
    {
        return $this->hasOne(EmergencyContact::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<CcaRecord, $this> */
    public function ccaRecords(): HasMany
    {
        return $this->hasMany(CcaRecord::class);
    }

    /** Uploaded override first, then the Google photo. */
    public function photoUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : $this->avatar_url;
    }

    public function coverUrl(): ?string
    {
        return $this->cover_path ? Storage::disk('public')->url($this->cover_path) : null;
    }

    /** @return list<string> */
    public function globalRoles(): array
    {
        return $this->getRoleNames()->values()->all();
    }

    public function isSldAdmin(): bool
    {
        return $this->hasAnyRole(['admin', 'superadmin']);
    }

    public function hidesSection(string $section): bool
    {
        return in_array($section, $this->hidden_sections ?? [], true);
    }
}
