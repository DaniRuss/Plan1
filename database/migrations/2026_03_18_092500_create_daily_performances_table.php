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
        if (Schema::hasTable('daily_performances')) {
            return;
        }

        Schema::create('daily_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_item_id')->constrained()->onDelete('cascade');
            $table->integer('year');
            $table->string('month');
            $table->integer('week_number');
            $table->integer('day_of_week'); // 1 = Monday, 7 = Sunday (or according to preferred system)
            $table->decimal('actual_value', 12, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['plan_item_id', 'year', 'month', 'week_number', 'day_of_week'], 'daily_perf_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_performances');
    }
};
