<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Usual yole vocabulary: "Dresseur" becomes "Bwa dressé", "Aide-patron" becomes "Pagaie".
 * Codes stay the same; only the labels shown in the app change.
 */
return new class extends Migration
{
    /** @var array<string, array{string, string}> code => [old label, new label] */
    private array $roles = [
        'dresseur' => ['Dresseur', 'Bwa dressé'],
        'aide_patron' => ['Aide-patron', 'Pagaie'],
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
        foreach ($this->roles as $code => [$old, $new]) {
            [$from, $to] = $reverse ? [$new, $old] : [$old, $new];

            DB::table('crew_roles')->where('code', $code)->update(['label' => $to]);
        }

        $pairs = $reverse
            ? [['Bwa dressé bâbord', 'Dresseur Bâbord'], ['Bwa dressé tribord', 'Dresseur Tribord'], ['Pagaie', 'Aide-patron']]
            : [['Dresseur Bâbord', 'Bwa dressé bâbord'], ['Dresseur Tribord', 'Bwa dressé tribord'], ['Aide-patron', 'Pagaie']];

        foreach ($pairs as [$from, $to]) {
            DB::table('boat_positions')
                ->where('label', 'like', $from.'%')
                ->update(['label' => DB::raw('REPLACE(label, '.DB::getPdo()->quote($from).', '.DB::getPdo()->quote($to).')')]);
        }
    }
};
