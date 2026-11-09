<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('historical_performance_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('year')->index();
            $table->string('original_filename');
            $table->char('source_hash', 64);
            $table->string('status', 30)->default('locked');
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['year', 'source_hash']);
        });

        Schema::create('historical_kpis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historical_performance_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('source_row');
            $table->string('code', 50)->nullable()->index();
            $table->string('original_code', 50)->nullable();
            $table->string('parent_code', 50)->nullable();
            $table->text('description');
            $table->decimal('weight', 12, 4)->nullable();
            $table->string('unit', 150)->nullable();
            $table->string('normalized_unit', 100)->nullable()->index();
            $table->decimal('annual_target', 20, 4)->nullable();
            $table->decimal('annual_actual', 20, 4)->nullable();
            $table->decimal('reported_achievement', 12, 4)->nullable();
            $table->string('goal_direction', 20)->default('increase');
            $table->string('direction_status', 30)->default('needs_review');
            $table->text('performers')->nullable();
            $table->boolean('is_main_objective')->default(false);
            $table->string('unit_status', 30)->default('specified');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['historical_performance_import_id', 'source_row'], 'historical_kpis_import_row_unique');
        });

        Schema::create('historical_kpi_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('historical_kpi_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_objective_id')->nullable()->constrained('strategic_objectives')->nullOnDelete();
            $table->string('mapping_type', 30)->default('direct');
            $table->boolean('comparable')->default(false);
            $table->decimal('confidence', 5, 2)->nullable();
            $table->text('expert_comment')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['historical_kpi_id', 'current_objective_id'], 'historical_mapping_pair_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_kpi_mappings');
        Schema::dropIfExists('historical_kpis');
        Schema::dropIfExists('historical_performance_imports');
    }
};
