<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->unique()->constrained('imports')->cascadeOnDelete();

            // Wagon & Yard (Stage 4)
            $table->string('wagon_id')->nullable();
            $table->string('yard_location')->nullable();
            $table->boolean('requires_escort')->default(false);

            // Transfer (Stage 5)
            $table->timestamp('transfer_date')->nullable();

            // FZ-specific
            $table->enum('storage_location', ['DCT', 'NAGAD', 'DMP'])->nullable();
            $table->boolean('get_pass_obtained')->default(false);

            // Machinery-specific
            $table->boolean('lashing_required')->default(false);
            $table->boolean('lashing_completed')->default(false);
            $table->text('special_handling_notes')->nullable();

            // Train Loading (Stage 8)
            $table->string('train_id')->nullable();
            $table->timestamp('loading_time')->nullable();

            // Operation Department (Stage 7)
            $table->timestamp('operation_approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_operations');
    }
};
