<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cargo_consignments', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_id')->unique();
            $table->string('consignment_number');
            $table->string('shipper_name');
            $table->string('consignee_name')->nullable();
            $table->string('goods_type')->nullable();
            $table->decimal('quantity', 10, 2)->nullable();
            $table->decimal('weight', 10, 2)->nullable(); // in tons
            $table->decimal('volume', 10, 2)->nullable(); // in m3
            $table->string('container_id')->nullable();
            $table->enum('current_status', [
                'Arrived Origin',
                'In Transit',
                'Arrived Destination',
                'Transferred',
                'Accepted',
                'Dispatched'
            ])->default('Arrived Origin');
            
            $table->string('origin_station');
            $table->string('destination_station');
            
            $table->foreignId('train_journey_id')->nullable()->constrained('train_journeys')->nullOnDelete();
            $table->foreignId('logistics_company_id')->nullable()->constrained('logistics_companies')->nullOnDelete();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cargo_consignments');
    }
};
