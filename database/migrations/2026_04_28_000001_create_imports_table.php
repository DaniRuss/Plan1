<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('job_id')->unique();
            $table->enum('cargo_type', ['CNTR', 'FZ', 'MACHINERY']);
            $table->enum('status', [
                'DOCUMENT_SUBMITTED',
                'ECD_VALIDATED',
                'RELEASE_DOC_RECEIVED',
                'WAGON_ASSIGNED',
                'AT_RAILWAY_YARD',
                'TRANSIT_DOC_READY',
                'OPERATION_APPROVED',
                'LOADED_ON_TRAIN',
                'DISPATCHED',
            ])->default('DOCUMENT_SUBMITTED');

            // Parties
            $table->string('shipper_name');
            $table->string('consignee_name')->nullable();
            $table->string('freight_forwarder')->nullable(); // Transitor

            // Cargo details
            $table->string('description')->nullable();
            $table->decimal('weight', 10, 2)->nullable();
            $table->integer('container_count')->nullable();
            $table->string('bl_number')->nullable();
            $table->string('vessel_name')->nullable();
            $table->string('port_of_origin')->nullable();

            // Assignment
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
