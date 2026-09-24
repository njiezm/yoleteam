<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Outing;
use Carbon\CarbonInterface;

/**
 * Records one member's status for an outing (null clears it). Used by the appel page and by offline sync.
 */
class AttendanceRecorder
{
    /**
     * @param  CarbonInterface|null  $at  moment of the change (offline replays keep the device time as updated_at)
     */
    public function record(Outing $outing, int $memberId, ?AttendanceStatus $status, int $userId, ?CarbonInterface $at = null): ?Attendance
    {
        $attendance = Attendance::withTrashed()->firstOrNew(['outing_id' => $outing->id, 'member_id' => $memberId]);

        if ($at) {
            $attendance->updated_at = $at;
        }

        if ($status === null) {
            if ($attendance->exists && ! $attendance->trashed()) {
                // Soft delete by hand so an offline replay keeps its own updated_at.
                $attendance->forceFill(['recorded_by' => $userId, 'deleted_at' => $at ?? now()])->save();
            }

            return null;
        }

        if ($attendance->trashed()) {
            $attendance->deleted_at = null;
        }

        $arrivedAt = $at ?? now();

        $attendance->fill([
            'status' => $status,
            'recorded_by' => $userId,
            'arrived_at' => $status->isOnSite() ? ($attendance->arrived_at ?? ($outing->date->isSameDay($arrivedAt) ? $arrivedAt : null)) : null,
        ])->save();

        return $attendance;
    }
}
