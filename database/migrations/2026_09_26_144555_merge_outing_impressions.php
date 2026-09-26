<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One "impressions de navigation" text instead of before / during / after: existing texts are merged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outings', function (Blueprint $table) {
            $table->text('impressions')->nullable()->after('notes');
        });

        DB::table('outings')
            ->where(fn ($query) => $query->whereNotNull('notes_before')->orWhereNotNull('notes_during')->orWhereNotNull('notes_after'))
            ->orderBy('id')
            ->each(function (object $outing) {
                $parts = collect(['Avant' => $outing->notes_before, 'Pendant' => $outing->notes_during, 'Après' => $outing->notes_after])
                    ->filter(fn ($text) => filled($text))
                    ->map(fn ($text, $label) => $label.' : '.trim($text));

                DB::table('outings')->where('id', $outing->id)->update(['impressions' => $parts->join("\n\n")]);
            });

        Schema::table('outings', function (Blueprint $table) {
            $table->dropColumn(['notes_before', 'notes_during', 'notes_after']);
        });
    }

    public function down(): void
    {
        Schema::table('outings', function (Blueprint $table) {
            $table->text('notes_before')->nullable()->after('notes');
            $table->text('notes_during')->nullable()->after('notes_before');
            $table->text('notes_after')->nullable()->after('notes_during');
        });

        DB::table('outings')->whereNotNull('impressions')->update(['notes_after' => DB::raw('impressions')]);

        Schema::table('outings', function (Blueprint $table) {
            $table->dropColumn('impressions');
        });
    }
};
