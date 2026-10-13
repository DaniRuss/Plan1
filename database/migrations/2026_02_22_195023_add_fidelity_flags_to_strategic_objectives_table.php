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
            $table->boolean('is_reference_only')->default(false)->after('weight');
            $table->boolean('is_calculated_weight')->default(false)->after('is_reference_only');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->dropColumn(['is_reference_only', 'is_calculated_weight']);
        });
    }
};
