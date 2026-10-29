<?php

namespace App\Models;

use App\Enums\ScopeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'logo_path', 'category_id', 'established_year', 'bio', 'instagram_handle', 'managed_by', 'transferred_at'])]
class Club extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['transferred_at' => 'datetime'];
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
