<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reporting_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('coordinator_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('backup_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('reporting_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('year');
            $table->string('month', 12);
            $table->string('frequency', 12);
            $table->unsignedTinyInteger('week')->default(0);
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->text('workflow_note')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['department_id','year','month','frequency','week'], 'reporting_period_unique');
        });
        Schema::create('reporting_review_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('reporting_submissions')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['submission_id','reviewer_id']);
        });
        Schema::create('reporting_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('reporting_submissions')->cascadeOnDelete();
            $table->foreignId('section_id')->nullable()->constrained('reporting_review_sections')->restrictOnDelete();
            $table->foreignId('plan_item_id')->constrained()->restrictOnDelete();
            $table->string('state')->default('missing');
            $table->unsignedInteger('version')->default(1);
            $table->text('reason')->nullable();
            $table->boolean('evidence_required')->default(false);
            $table->json('evidence_paths')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['submission_id','plan_item_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('reporting_entries');
        Schema::dropIfExists('reporting_review_sections');
        Schema::dropIfExists('reporting_submissions');
        Schema::dropIfExists('reporting_teams');
    }
};
