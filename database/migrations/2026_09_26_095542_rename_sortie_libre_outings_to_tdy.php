<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Sortie libre" is replaced by the TDY (Tour des yoles) outing type.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('outings')->where('type', 'sortie_libre')->update(['type' => 'tdy']);
    }

    public function down(): void
    {
        DB::table('outings')->where('type', 'tdy')->update(['type' => 'sortie_libre']);
    }
};
