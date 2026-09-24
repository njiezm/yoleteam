<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Members deleted before this fix could still be seated in today's or upcoming crew plans, which broke the
 * outing page: free those seats (past plans keep their history).
 */
return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('crew_assignments')
            ->join('members', 'members.id', '=', 'crew_assignments.member_id')
            ->join('crew_plans', 'crew_plans.id', '=', 'crew_assignments.crew_plan_id')
            ->join('outings', 'outings.id', '=', 'crew_plans.outing_id')
            ->whereNotNull('members.deleted_at')
            ->whereNull('crew_assignments.deleted_at')
            ->whereDate('outings.date', '>=', now()->toDateString())
            ->pluck('crew_assignments.id');

        DB::table('crew_assignments')->whereIn('id', $ids)->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Freed seats are not restored.
    }
};
