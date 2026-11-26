<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 30);
            $table->string('auditable_type', 120)->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('summary', 255)->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['event', 'created_at']);
            $table->index('user_id');
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 20);
            $table->string('permission', 60);
            $table->boolean('allowed');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['role', 'permission']);
        });

        Schema::create('kebele_weekly_actuals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kebele_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->date('week_start');
            $table->foreignId('plan_activity_id')->constrained('plan_activities')->restrictOnDelete();
            $table->decimal('value', 16, 3);
            $table->timestamps();

            $table->unique(['kebele_id', 'week_start', 'plan_activity_id'], 'kebele_weekly_unique');
            $table->index(['week_start', 'plan_activity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kebele_weekly_actuals');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('audit_logs');
    }
};
