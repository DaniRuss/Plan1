<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'description')) {
                $table->text('description')->nullable()->after('name');
            }
            if (!Schema::hasColumn('projects', 'start_date')) {
                $table->date('start_date')->nullable()->after('description');
            }
            if (!Schema::hasColumn('projects', 'end_date')) {
                $table->date('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('projects', 'total_budget')) {
                $table->decimal('total_budget', 15, 2)->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('projects', 'currency')) {
                $table->string('currency', 10)->default('ETB')->after('total_budget');
            }
        });

        if (!Schema::hasTable('project_milestones')) {
            Schema::create('project_milestones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->onDelete('cascade');
                $table->string('title');
                $table->text('description')->nullable();
                $table->date('due_date')->nullable();
                $table->enum('status', ['pending', 'in_progress', 'completed', 'delayed'])->default('pending');
                $table->decimal('completion_percentage', 5, 2)->default(0);
                $table->decimal('weight', 5, 2)->default(1);
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_milestones');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['description', 'start_date', 'end_date', 'total_budget', 'currency']);
        });
    }
};
