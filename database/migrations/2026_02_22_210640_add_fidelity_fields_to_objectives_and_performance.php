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
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->string('template_type')->default('standard')->after('unit'); // standard, machinery, truck_haulage, revenue
            $table->string('data_source')->nullable()->after('template_type'); // e.g. "Nagad/SGTD/DMP"
            $table->string('summary_type')->default('weighted')->after('data_source'); // weighted, sum, average, manual_override
        });

        Schema::table('weekly_performances', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('actual_value'); // Stores Qty, Time, Days, etc.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->dropColumn(['template_type', 'data_source', 'summary_type']);
        });

        Schema::table('weekly_performances', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};
