<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('department_plan_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('year');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('department_plan_submissions'); }
};
