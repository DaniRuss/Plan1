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
        Schema::create('strategic_objectives', function (Blueprint $table) {
            $table->id();
            $table->string('code')->index(); // e.g. 14, 14.1
            $table->text('description');     // The objective text
            $table->decimal('weight', 8, 4)->default(0);
            $table->string('unit')->nullable(); // %, No., Tons
            $table->decimal('baseline_value', 12, 4)->nullable();
            $table->string('baseline_year')->nullable(); // 2017 EFY
            $table->decimal('annual_target', 12, 4)->nullable();

            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('strategic_objectives')->nullOnDelete();

            $table->json('performers')->nullable(); // Array of department IDs/names
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('strategic_objectives');
    }
};
