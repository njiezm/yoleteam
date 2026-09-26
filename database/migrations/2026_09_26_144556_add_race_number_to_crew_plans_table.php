<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Championship days: one crew plan per boat and per race (manche), since the crew changes between races.
 * Other outings keep a single plan per boat (race 1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crew_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('race_number')->default(1)->after('boat_id');
        });

        Schema::table('crew_plans', function (Blueprint $table) {
            $table->dropUnique(['outing_id', 'boat_id']);
            $table->unique(['outing_id', 'boat_id', 'race_number']);
        });
    }

    public function down(): void
    {
        Schema::table('crew_plans', function (Blueprint $table) {
            $table->dropUnique(['outing_id', 'boat_id', 'race_number']);
        });

        Schema::table('crew_plans', function (Blueprint $table) {
            $table->dropColumn('race_number');
            $table->unique(['outing_id', 'boat_id']);
        });
    }
};
