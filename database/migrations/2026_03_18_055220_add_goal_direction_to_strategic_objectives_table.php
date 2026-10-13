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
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->string('goal_direction')->default('increase')->after('annual_target');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->dropColumn('goal_direction');
        });
    }
};
