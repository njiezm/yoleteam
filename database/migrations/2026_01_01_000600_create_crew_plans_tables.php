<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('outing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boat_id')->constrained()->restrictOnDelete();
            $table->foreignId('boat_configuration_id')->constrained()->restrictOnDelete();
            $table->smallInteger('wind_direction')->nullable();
            $table->unsignedSmallInteger('wind_strength')->nullable();
            $table->string('status', 20)->default('brouillon');
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['outing_id', 'boat_id']);
            $table->index('updated_at');
        });

        Schema::create('crew_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('crew_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('boat_position_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->string('bwa_placement', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('member_id');
            $table->index('updated_at');
        });

        // Partial unique indexes so soft-deleted rows never block a re-assignment (PostgreSQL).
        DB::statement('CREATE UNIQUE INDEX crew_assignments_plan_position_unique ON crew_assignments (crew_plan_id, boat_position_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX crew_assignments_plan_member_unique ON crew_assignments (crew_plan_id, member_id) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_assignments');
        Schema::dropIfExists('crew_plans');
    }
};
