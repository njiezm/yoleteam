<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Races (manches) sailed during a race outing: place, outcome and points.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outing_races', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outing_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->unsignedSmallInteger('place')->nullable();
            $table->string('result', 20)->default('classe');
            $table->unsignedSmallInteger('points')->nullable();
            $table->timestamps();

            $table->unique(['outing_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outing_races');
    }
};
