<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('association_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('type', 20)->default('entrainement');
            $table->string('title');
            $table->date('date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 20)->default('planifiee');
            $table->foreignId('race_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['association_id', 'date']);
            $table->index(['association_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outings');
    }
};
