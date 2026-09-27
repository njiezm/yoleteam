<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Several boats on one outing: whether a rower already seated on one boat can also be seated on another
 * (padlock open) or not (padlock closed, the default).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outings', function (Blueprint $table) {
            $table->boolean('share_crew')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('outings', function (Blueprint $table) {
            $table->dropColumn('share_crew');
        });
    }
};
