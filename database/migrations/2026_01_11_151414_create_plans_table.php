<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->enum('type', ['annual', 'quarterly', 'monthly', 'weekly', 'daily']);
            $table->year('year');
            $table->tinyInteger('quarter')->nullable(); // 1-4
            $table->tinyInteger('month')->nullable(); // 1-12
            $table->tinyInteger('week')->nullable(); // 1-53
            $table->date('plan_date')->nullable(); // for daily plans

            // Department that owns this plan
            $table->foreignId('department_id')->constrained()->onDelete('cascade');

            // Status and approval
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'archived'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // Creator
            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();

            // Ensure unique plans per period
            $table->unique(['department_id', 'type', 'year', 'quarter', 'month', 'week', 'plan_date'], 'unique_plan_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
