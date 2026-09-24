<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('races', function (Blueprint $table) {
            $table->id();
            $table->foreignId('association_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 30)->default('regate');
            $table->unsignedSmallInteger('season')->index();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('race_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('race_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('name');
            $table->date('date');
            $table->string('start_location')->nullable();
            $table->string('end_location')->nullable();
            $table->decimal('distance_nm', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['race_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('race_stages');
        Schema::dropIfExists('races');
    }
};
