<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('race_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boat_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crew_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('rank')->nullable();
            $table->unsignedInteger('elapsed_seconds')->nullable();
            $table->decimal('points', 6, 2)->nullable();
            $table->string('status', 20)->default('classe');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['race_stage_id', 'boat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('race_results');
    }
};
