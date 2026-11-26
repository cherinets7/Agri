<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 10)->default('kebele')->after('password');
            $table->foreignId('location_id')->nullable()->after('role')->constrained('locations')->restrictOnDelete();
            $table->string('phone', 30)->nullable()->after('location_id');
            $table->boolean('is_active')->default(true)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['role', 'phone', 'is_active']);
        });
    }
};
