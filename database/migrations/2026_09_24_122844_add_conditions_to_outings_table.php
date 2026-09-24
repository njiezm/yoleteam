<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Navigation conditions of an outing: wind, sea and weather (shared by every crew plan of the outing).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outings', function (Blueprint $table) {
            $table->smallInteger('wind_direction')->nullable()->after('location');
            $table->unsignedSmallInteger('wind_strength')->nullable()->after('wind_direction');
            $table->unsignedSmallInteger('wind_gusts')->nullable()->after('wind_strength');
            $table->string('sea_state', 20)->nullable()->after('wind_gusts');
            $table->decimal('swell_m', 3, 1)->nullable()->after('sea_state');
            $table->string('weather')->nullable()->after('swell_m');
        });
    }

    public function down(): void
    {
        Schema::table('outings', function (Blueprint $table) {
            $table->dropColumn(['wind_direction', 'wind_strength', 'wind_gusts', 'sea_state', 'swell_m', 'weather']);
        });
    }
};
