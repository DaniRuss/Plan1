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
        Schema::create('periodic_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_item_id')->constrained()->onDelete('cascade');
            $table->string('period'); // 'july', 'august', ... 'q1', 'q2'
            $table->decimal('actual_value', 12, 2);
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Constraint: One record per period per item
            $table->unique(['plan_item_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodic_performances');
    }
};
