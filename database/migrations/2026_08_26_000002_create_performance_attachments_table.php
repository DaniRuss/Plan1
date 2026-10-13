<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('month', 20);
            $table->unsignedTinyInteger('week_number')->nullable();
            $table->enum('frequency', ['weekly', 'monthly']);
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['department_id', 'year', 'month', 'week_number'], 'performance_attachment_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_attachments');
    }
};
