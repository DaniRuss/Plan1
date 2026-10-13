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
        Schema::table('plan_items', function (Blueprint $table) {
            $table->dropColumn(['q1_target', 'q2_target', 'q3_target', 'q4_target']);
        });

        Schema::table('plan_items', function (Blueprint $table) {
            $table->decimal('q1_target', 12, 2)->nullable();
            $table->decimal('q2_target', 12, 2)->nullable();
            $table->decimal('q3_target', 12, 2)->nullable();
            $table->decimal('q4_target', 12, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_items', function (Blueprint $table) {
            $table->dropColumn(['q1_target', 'q2_target', 'q3_target', 'q4_target']);
        });

        // Re-adding generated logic would require raw SQL again, 
        // simplified rollback just removes these.
    }
};
