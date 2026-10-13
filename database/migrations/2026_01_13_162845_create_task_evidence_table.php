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
        Schema::create('task_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_report_id')->constrained()->onDelete('cascade');
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->string('file_type', 50)->nullable(); // 'document', 'image', 'spreadsheet'
            $table->bigInteger('file_size')->nullable(); // bytes
            $table->foreignId('uploaded_by')->constrained('employees')->onDelete('cascade');
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_evidence');
    }
};
