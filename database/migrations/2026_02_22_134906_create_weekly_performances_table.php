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
        Schema::create('weekly_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_item_id')->constrained('plan_items')->onDelete('cascade');
            $table->unsignedSmallInteger('year');
            $table->string('month', 20); // january, february, etc.
            $table->unsignedTinyInteger('week_number'); // 1-5
            $table->decimal('planned_value', 16, 12);
            $table->decimal('actual_value', 16, 12);
            $table->decimal('percentage', 16, 12)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_performances');
    }
};
