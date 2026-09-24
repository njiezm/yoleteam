<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('association_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nickname')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();
            $table->decimal('weight_kg', 5, 1)->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->string('level', 20)->default('debutant');
            $table->string('category', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['association_id', 'updated_at']);
        });

        Schema::create('crew_role_member', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crew_role_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();

            $table->unique(['member_id', 'crew_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_role_member');
        Schema::dropIfExists('members');
    }
};
