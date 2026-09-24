<?php

namespace App\Models\Concerns;

use App\Models\Association;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * For models carrying an `association_id` column (multi-tenant ready).
 */
trait BelongsToAssociation
{
    public static function bootBelongsToAssociation(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->association_id) && ($associationId = Auth::user()?->association_id)) {
                $model->association_id = $associationId;
            }
        });
    }

    /**
     * @return BelongsTo<Association, $this>
     */
    public function association(): BelongsTo
    {
        return $this->belongsTo(Association::class);
    }

    /**
     * Route model binding only resolves records of the signed-in user's association.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if ($associationId = Auth::user()?->association_id) {
            $query->where($this->qualifyColumn('association_id'), $associationId);
        }

        return $query;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForAssociation(Builder $query, Association|int $association): void
    {
        $query->where(
            $this->qualifyColumn('association_id'),
            $association instanceof Association ? $association->getKey() : $association,
        );
    }
}
