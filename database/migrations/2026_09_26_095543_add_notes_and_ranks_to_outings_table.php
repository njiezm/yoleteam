<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Impressions before / during / after the outing, distance sailed, and the rankings of race and TDY outings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outings', function (Blueprint $table) {
            $table->text('notes_before')->nullable()->after('notes');
            $table->text('notes_during')->nullable()->after('notes_before');
            $table->text('notes_after')->nullable()->after('notes_during');
            $table->decimal('distance_nm', 5, 1)->nullable()->after('notes_after');
            $table->unsignedSmallInteger('day_rank')->nullable()->after('distance_nm');
            $table->unsignedSmallInteger('stage_rank')->nullable()->after('day_rank');
            $table->unsignedSmallInteger('general_rank')->nullable()->after('stage_rank');
        });
    }

    public function down(): void
    {
        Schema::table('outings', function (Blueprint $table) {
            $table->dropColumn(['notes_before', 'notes_during', 'notes_after', 'distance_nm', 'day_rank', 'stage_rank', 'general_rank']);
        });
    }
};
