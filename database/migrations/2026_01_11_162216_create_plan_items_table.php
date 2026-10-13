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
        Schema::create('plan_items', function (Blueprint $table) {
            $table->id();

            // Link to plan
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');

            // Link to strategic objective/activity
            $table->foreignId('strategic_objective_id')->constrained()->onDelete('cascade');

            // Annual target
            $table->decimal('annual_target', 12, 2)->default(0);

            // Monthly targets (your Excel columns)
            $table->decimal('july_target', 12, 2)->nullable();
            $table->decimal('august_target', 12, 2)->nullable();
            $table->decimal('september_target', 12, 2)->nullable();
            $table->decimal('october_target', 12, 2)->nullable();
            $table->decimal('november_target', 12, 2)->nullable();
            $table->decimal('december_target', 12, 2)->nullable();
            $table->decimal('january_target', 12, 2)->nullable();
            $table->decimal('february_target', 12, 2)->nullable();
            $table->decimal('march_target', 12, 2)->nullable();
            $table->decimal('april_target', 12, 2)->nullable();
            $table->decimal('may_target', 12, 2)->nullable();
            $table->decimal('june_target', 12, 2)->nullable();

            // Generated columns for quarters (auto-calculated)
            $table->decimal('q1_target', 12, 2)->nullable();
            $table->decimal('q2_target', 12, 2)->nullable();
            $table->decimal('q3_target', 12, 2)->nullable();
            $table->decimal('q4_target', 12, 2)->nullable();

            // Weight for this activity in this plan
            $table->decimal('weight', 5, 2)->default(0); // percentage

            // Baseline (from strategic objective)
            $table->decimal('baseline_value', 12, 2)->nullable();
            $table->year('baseline_year')->nullable();

            // Unit of measurement
            $table->enum('unit', ['percent', 'number', 'tons', 'currency', 'days', 'na'])->default('percent');

            // Performers (users/departments responsible)
            $table->json('performers')->nullable();

            $table->timestamps();

            // Ensure unique objective per plan
            $table->unique(['plan_id', 'strategic_objective_id']);
        });

        // Add generated columns for quarter calculations
        // Note: IF NOT EXISTS checks might be needed in a raw SQL migration, but here we are in create()
        // so table is fresh.
        // We use DB::statement for generated columns support across different DBs if Laravel doesn't support it natively yet well enough or to be explicit.

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            \DB::statement('
                ALTER TABLE plan_items 
                MODIFY COLUMN q1_target DECIMAL(12,2) 
                GENERATED ALWAYS AS (COALESCE(july_target, 0) + COALESCE(august_target, 0) + COALESCE(september_target, 0)) STORED
            ');

            \DB::statement('
                ALTER TABLE plan_items 
                MODIFY COLUMN q2_target DECIMAL(12,2) 
                GENERATED ALWAYS AS (COALESCE(october_target, 0) + COALESCE(november_target, 0) + COALESCE(december_target, 0)) STORED
            ');

            \DB::statement('
                ALTER TABLE plan_items 
                MODIFY COLUMN q3_target DECIMAL(12,2) 
                GENERATED ALWAYS AS (COALESCE(january_target, 0) + COALESCE(february_target, 0) + COALESCE(march_target, 0)) STORED
            ');

            \DB::statement('
                ALTER TABLE plan_items 
                MODIFY COLUMN q4_target DECIMAL(12,2) 
                GENERATED ALWAYS AS (COALESCE(april_target, 0) + COALESCE(may_target, 0) + COALESCE(june_target, 0)) STORED
            ');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_items');
    }
};
