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
        Schema::create('train_journeys', function (Blueprint $table) {
            $table->id();
            $table->string('train_number');
            $table->string('locomotive_number')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('origin_station');
            $table->string('destination_station');
            $table->timestamp('departure_time')->nullable();
            $table->timestamp('arrival_time')->nullable();
            $table->string('status')->default('scheduled'); // scheduled, in_transit, completed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('train_journeys');
    }
};
