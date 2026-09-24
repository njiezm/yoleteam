<?php

namespace App\Models;

use Database\Factories\AssociationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'city', 'logo_path', 'primary_color', 'settings'])]
class Association extends Model
{
    /** @use HasFactory<AssociationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /** @return HasMany<Boat, $this> */
    public function boats(): HasMany
    {
        return $this->hasMany(Boat::class);
    }

    /** @return HasMany<Outing, $this> */
    public function outings(): HasMany
    {
        return $this->hasMany(Outing::class);
    }

    /** @return HasMany<Race, $this> */
    public function races(): HasMany
    {
        return $this->hasMany(Race::class);
    }

    /** @return HasMany<SyncOperation, $this> */
    public function syncOperations(): HasMany
    {
        return $this->hasMany(SyncOperation::class);
    }
}
