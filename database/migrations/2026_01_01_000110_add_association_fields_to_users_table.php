<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('association_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 20)->default('patron')->index();
            $table->string('phone', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('association_id');
            $table->dropColumn(['role', 'phone']);
        });
    }
};
