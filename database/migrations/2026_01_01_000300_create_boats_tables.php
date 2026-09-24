<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('association_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('sponsor')->nullable();
            $table->string('hull_color', 30)->nullable();
            $table->decimal('length_m', 4, 2)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('boat_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boat_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sail_count')->default(1);
            $table->unsignedSmallInteger('bwa_count')->default(3);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('boat_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('boat_configuration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crew_role_id')->constrained()->restrictOnDelete();
            $table->string('code', 60);
            $table->string('label');
            $table->string('side', 10)->default('centre');
            $table->unsignedSmallInteger('bwa_index')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('x', 5, 2);
            $table->decimal('y', 5, 2);
            $table->boolean('is_optional')->default(false);
            $table->timestamps();

            $table->unique(['boat_configuration_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_positions');
        Schema::dropIfExists('boat_configurations');
        Schema::dropIfExists('boats');
    }
};
