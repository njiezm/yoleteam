<?php

use App\Models\BoatConfiguration;
use App\Services\BoatLayoutGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Real crew composition of a yole ronde:
 * - misaine (1 voile): patron + 2 pagaies, 2 écoutes, 9 bwa dressés; 2 voiles: + 2 cordes, 4 écoutes, 8 bwa dressés;
 * - all bwa dressés sit on the windward side (chosen per crew plan, switched at each tack);
 * - fonds / écopeurs: number chosen per crew plan.
 * Existing seats are renamed to the new codes so that crew already placed keeps its place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boat_configurations', function (Blueprint $table) {
            $table->unsignedSmallInteger('cordes_count')->nullable()->after('bwa_count');
            $table->unsignedSmallInteger('ecoute_count')->nullable()->after('cordes_count');
            $table->unsignedSmallInteger('pagaie_count')->nullable()->after('ecoute_count');
        });

        Schema::table('crew_plans', function (Blueprint $table) {
            $table->string('bwa_side', 10)->default('babord')->after('wind_strength');
            $table->unsignedSmallInteger('fond_count')->default(1)->after('bwa_side');
        });

        if (! DB::table('crew_roles')->exists()) {
            return; // Fresh database: seeders generate the new layout directly.
        }

        $generator = app(BoatLayoutGenerator::class);

        BoatConfiguration::query()->each(function (BoatConfiguration $configuration) use ($generator) {
            DB::transaction(function () use ($configuration, $generator) {
                $positions = DB::table('boat_positions')->where('boat_configuration_id', $configuration->id);

                foreach ((clone $positions)->get(['id', 'code']) as $position) {
                    $code = match (true) {
                        (bool) preg_match('/^dresseur_babord_(\d+)$/', $position->code, $m) => 'bwa_'.(2 * $m[1] - 1),
                        (bool) preg_match('/^dresseur_tribord_(\d+)$/', $position->code, $m) => 'bwa_'.(2 * $m[1]),
                        $position->code === 'ecoute' => $configuration->sail_count >= 2 ? 'ecoute_gv_1' : 'ecoute_1',
                        $position->code === 'ecopeur' => 'fond_1',
                        default => null,
                    };

                    if ($code) {
                        DB::table('boat_positions')->where('id', $position->id)->update(['code' => $code]);
                    }
                }

                $configuration->forceFill(['bwa_count' => BoatLayoutGenerator::defaults($configuration->sail_count)['bwa_count']])->save();
                $generator->generate($configuration->fresh());
            });
        });

        // Plans keep the fond seat they already used (0 when none was assigned).
        DB::table('crew_plans')->update(['fond_count' => 0]);
        DB::table('crew_assignments')
            ->join('boat_positions', 'boat_positions.id', '=', 'crew_assignments.boat_position_id')
            ->where('boat_positions.code', 'fond_1')
            ->whereNull('crew_assignments.deleted_at')
            ->pluck('crew_assignments.crew_plan_id')
            ->each(fn (int $planId) => DB::table('crew_plans')->where('id', $planId)->update(['fond_count' => 1]));
    }

    /**
     * Only the columns are removed: renamed and regenerated seats are not converted back.
     */
    public function down(): void
    {
        Schema::table('crew_plans', function (Blueprint $table) {
            $table->dropColumn(['bwa_side', 'fond_count']);
        });

        Schema::table('boat_configurations', function (Blueprint $table) {
            $table->dropColumn(['cordes_count', 'ecoute_count', 'pagaie_count']);
        });
    }
};
