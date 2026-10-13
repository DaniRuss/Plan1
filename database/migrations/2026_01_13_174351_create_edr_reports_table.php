<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('edr_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained();
            $table->string('reporting_period'); // Q1, Q2, Q3, Q4, Annual
            $table->integer('year');

            // Narrative Sections
            $table->text('executive_summary')->nullable();
            $table->text('introduction')->nullable();
            $table->text('challenges')->nullable();
            $table->text('solutions')->nullable();
            $table->text('non_planned_activities')->nullable();
            $table->text('strengths')->nullable();
            $table->text('weaknesses')->nullable();
            $table->text('best_practices')->nullable();
            $table->text('next_focus_areas')->nullable();

            // Workflow fields
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->foreignId('submitted_by')->nullable()->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('edr_reports');
    }
};
