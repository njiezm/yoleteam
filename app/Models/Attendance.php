<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\HasClientUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'outing_id', 'member_id', 'status', 'arrived_at', 'comment', 'recorded_by'])]
class Attendance extends Model
{
    use HasClientUuid, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'arrived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Outing, $this> */
    public function outing(): BelongsTo
    {
        return $this->belongsTo(Outing::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
