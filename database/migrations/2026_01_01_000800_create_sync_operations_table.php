<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('association_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_id', 100);
            $table->string('entity', 50);
            $table->uuid('entity_uuid');
            $table->string('action', 10);
            $table->jsonb('payload');
            $table->timestamp('client_updated_at');
            $table->timestamp('applied_at')->nullable();
            $table->string('status', 20)->default('applied');
            $table->jsonb('conflict_details')->nullable();
            $table->timestamps();

            $table->index(['entity', 'entity_uuid']);
            $table->index(['association_id', 'status']);
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
