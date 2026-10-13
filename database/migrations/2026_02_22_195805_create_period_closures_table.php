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
        Schema::create('period_closures', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->string('month'); // lower case e.g. 'july'
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certified_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('certified_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['year', 'month', 'department_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('period_closures');
    }
};
