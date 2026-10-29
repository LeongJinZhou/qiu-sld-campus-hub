<?php

namespace App\Models;

use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'logo_path', 'category_id', 'established_year', 'bio', 'instagram_handle', 'managed_by', 'transferred_at', 'status', 'last_activity_at'])]
class Club extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['transferred_at' => 'datetime', 'last_activity_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Club $club): void {
            $club->qr_token ??= Str::random(32);
            $club->slug ??= Str::slug($club->name);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<ClubCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ClubCategory::class);
    }

    /** @return HasMany<Event, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return HasMany<ClubFee, $this> */
    public function fees(): HasMany
    {
        return $this->hasMany(ClubFee::class);
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function ledger(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** @return HasOne<ClubOpeningBalance, $this> */
    public function openingBalance(): HasOne
    {
        return $this->hasOne(ClubOpeningBalance::class);
    }

    /** @return HasMany<Meeting, $this> */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** @return HasMany<Story, $this> */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    /** @return HasMany<Highlight, $this> */
    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class)->orderBy('sort');
    }

    /** @return HasMany<Album, $this> */
    public function albums(): HasMany
    {
        return $this->hasMany(Album::class);
    }

    /** @return HasMany<ClubFaq, $this> */
    public function faqs(): HasMany
    {
        return $this->hasMany(ClubFaq::class)->orderBy('sort');
    }

    /** @return BelongsToMany<User, $this> */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'club_follows');
    }

    /** @return HasMany<RoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(RoleAssignment::class, 'scope_id')->where('scope_type', ScopeType::Club);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function isTransferred(): bool
    {
        return $this->managed_by === 'committee';
    }
}
