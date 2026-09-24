<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Secondary `uuid` column used as the client-side (offline / IndexedDB) identifier.
 * The integer `id` stays the primary key; the uuid is generated when missing.
 *
 * @method static Builder<static> whereUuid(string $uuid)
 */
trait HasClientUuid
{
    public static function bootHasClientUuid(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public static function findByUuid(string $uuid, bool $withTrashed = false): ?static
    {
        $query = static::query();

        if ($withTrashed && method_exists(static::class, 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        return $query->where('uuid', $uuid)->first();
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeWhereUuid(Builder $query, string $uuid): void
    {
        $query->where($this->qualifyColumn('uuid'), $uuid);
    }

    /**
     * Records changed since the given moment (including soft-deleted ones, for sync pulls).
     *
     * @param  Builder<static>  $query
     */
    public function scopeChangedSince(Builder $query, \DateTimeInterface|string|null $since): void
    {
        if (method_exists($this, 'bootSoftDeletes')) {
            $query->withTrashed();
        }

        if ($since !== null) {
            $query->where($this->qualifyColumn('updated_at'), '>', $since);
        }
    }
}
