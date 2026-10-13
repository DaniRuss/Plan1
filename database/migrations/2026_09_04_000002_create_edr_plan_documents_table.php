<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('edr_plan_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('year')->unique();
            $table->text('introduction')->nullable();
            $table->text('past_performance')->nullable();
            $table->text('strategic_issues')->nullable();
            $table->text('implementation_strategies')->nullable();
            $table->text('monitoring_evaluation')->nullable();
            $table->text('challenges_solutions')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edr_plan_documents');
    }
};
