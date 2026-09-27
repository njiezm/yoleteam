<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "1ère corde" / "2ème corde" become "Corde poitier 1" / "Corde poitier 2". Codes stay the same.
 */
return new class extends Migration
{
    /** @var array<string, array{string, string}> code => [old label, new label] */
    private array $labels = [
        'premiere_corde' => ['1ère corde', 'Corde poitier 1'],
        'deuxieme_corde' => ['2ème corde', 'Corde poitier 2'],
    ];

    public function up(): void
    {
        $this->rename(false);
    }

    public function down(): void
    {
        $this->rename(true);
    }

    private function rename(bool $reverse): void
    {
        foreach ($this->labels as $code => [$old, $new]) {
            [$from, $to] = $reverse ? [$new, $old] : [$old, $new];

            DB::table('crew_roles')->where('code', $code)->update(['label' => $to]);
            DB::table('boat_positions')->where('code', $code)->where('label', $from)->update(['label' => $to]);
        }
    }
};
