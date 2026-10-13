<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('period_weight_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('period', 20);
            $table->string('mode', 20)->default('manual');
            $table->string('status', 50)->default('draft');
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('directorate_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('directorate_reviewed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('change_reason')->nullable();
            $table->timestamps();
            $table->unique(['department_id', 'year', 'period'], 'period_weight_distribution_unique');
        });

        Schema::create('period_weight_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained('period_weight_distributions')->cascadeOnDelete();
            $table->foreignId('plan_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('original_weight', 12, 4);
            $table->decimal('effective_weight', 12, 4)->nullable();
            $table->timestamps();
            $table->unique(['distribution_id', 'plan_item_id'], 'period_weight_allocation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_weight_allocations');
        Schema::dropIfExists('period_weight_distributions');
    }
};
