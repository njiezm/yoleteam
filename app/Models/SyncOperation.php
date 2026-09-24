<?php

namespace App\Models;

use App\Enums\SyncAction;
use App\Enums\SyncStatus;
use App\Models\Concerns\BelongsToAssociation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal of offline operations pushed by client devices (uuid primary key,
 * usually the client-generated operation id for idempotency).
 */
#[Fillable([
    'id', 'association_id', 'user_id', 'device_id', 'entity', 'entity_uuid', 'action', 'payload',
    'client_updated_at', 'applied_at', 'status', 'conflict_details',
])]
class SyncOperation extends Model
{
    use BelongsToAssociation, HasUuids {
        // Tenant scoping wins; the uuid format is already enforced by the route pattern.
        BelongsToAssociation::resolveRouteBindingQuery insteadof HasUuids;
    }

    protected function casts(): array
    {
        return [
            'action' => SyncAction::class,
            'status' => SyncStatus::class,
            'payload' => 'array',
            'conflict_details' => 'array',
            'client_updated_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
