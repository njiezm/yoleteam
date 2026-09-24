<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['association_id', 'name', 'email', 'phone', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'disabled_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Association, $this> */
    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    /** @return HasMany<Outing, $this> */
    public function createdOutings(): HasMany
    {
        return $this->hasMany(Outing::class, 'created_by');
    }

    /** @return HasMany<SyncOperation, $this> */
    public function syncOperations(): HasMany
    {
        return $this->hasMany(SyncOperation::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isPatron(): bool
    {
        return $this->role === UserRole::Patron;
    }

    /** Platform operator: every association and every account (see Gate "super-admin"). */
    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }
}
