<?php

use App\Models\BoatConfiguration;
use App\Services\BoatLayoutGenerator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * New seat layout of the drawing (2nd corde right behind the 1st, écoutes by their sail, fonds between two bwa,
 * pagaies then patron in line at the stern). Codes do not change, so placed crew keeps its seats.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('crew_roles')->exists()) {
            return;
        }

        $generator = app(BoatLayoutGenerator::class);
        BoatConfiguration::query()->each(fn (BoatConfiguration $configuration) => $generator->generate($configuration));
    }

    public function down(): void
    {
        // Coordinates only: nothing to undo.
    }
};
