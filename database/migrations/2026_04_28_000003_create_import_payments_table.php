<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->enum('payment_type', [
                'port_fee',
                'escort_fee',    // Yard 8 / CNTR
                'transfer_fee',  // Yard 8 / CNTR
                'lashing_fee',   // Machinery
                'loading_fee',   // Machinery
                'storage_fee',   // FZ
            ]);
            $table->decimal('amount', 12, 2)->nullable();
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->string('receipt_number')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_payments');
    }
};
