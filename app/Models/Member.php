<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\MemberCategory;
use App\Enums\MemberLevel;
use App\Models\Concerns\BelongsToAssociation;
use App\Models\Concerns\HasClientUuid;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable([
    'association_id', 'uuid', 'first_name', 'last_name', 'nickname', 'photo_path', 'phone', 'email',
    'birth_date', 'gender', 'weight_kg', 'height_cm', 'level', 'category', 'is_active', 'notes',
])]
class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use BelongsToAssociation, HasClientUuid, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => Gender::class,
            'weight_kg' => 'decimal:1',
            'height_cm' => 'integer',
            'level' => MemberLevel::class,
            'category' => MemberCategory::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return Attribute<string, never> */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => trim("{$this->first_name} {$this->last_name}"));
    }

    /** @return Attribute<string, never> */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn (): string => $this->nickname ?: $this->first_name);
    }

    /** @return Attribute<string, never> */
    protected function shortName(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.mb_substr($this->last_name, 0, 1).'.'));
    }

    /** @return Attribute<string, never> */
    protected function initials(): Attribute
    {
        return Attribute::get(fn (): string => mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->last_name, 0, 1)));
    }

    /**
     * Crew roles ordered preferred first, then by reference order (uses the loaded relation).
     *
     * @return Collection<int, CrewRole>
     */
    public function orderedCrewRoles(): Collection
    {
        return $this->crewRoles
            ->sortBy(fn (CrewRole $role) => [$role->pivot->is_preferred ? 0 : 1, $role->sort_order])
            ->values();
    }

    public function primaryCrewRole(): ?CrewRole
    {
        return $this->orderedCrewRoles()->first();
    }

    /** Avatar colour: the colour of the member's main crew role. */
    public function color(): string
    {
        return $this->primaryCrewRole()?->color ?? '#64748B';
    }

    /** Weight without trailing zeros, French decimal comma ("74" / "74,5"), or null. */
    public function formattedWeight(): ?string
    {
        return $this->weight_kg === null ? null : str_replace('.', ',', (string) (float) $this->weight_kg);
    }

    /** @return BelongsToMany<CrewRole, $this> */
    public function crewRoles(): BelongsToMany
    {
        return $this->belongsToMany(CrewRole::class)
            ->withPivot('is_preferred')
            ->withTimestamps();
    }

    /** @return BelongsToMany<CrewRole, $this> */
    public function preferredCrewRoles(): BelongsToMany
    {
        return $this->crewRoles()->wherePivot('is_preferred', true);
    }

    /** @return HasMany<Attendance, $this> */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<CrewAssignment, $this> */
    public function crewAssignments(): HasMany
    {
        return $this->hasMany(CrewAssignment::class);
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
