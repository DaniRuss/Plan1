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
        Schema::create('weekly_station_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_performance_id')->constrained('weekly_performances')->onDelete('cascade');
            $table->string('station_name');
            $table->decimal('planned_value', 16, 12);
            $table->decimal('actual_value', 16, 12);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_station_data');
    }
};
