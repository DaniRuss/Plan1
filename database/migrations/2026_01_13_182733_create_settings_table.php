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
        Schema::create('settings', function (Blueprint $create) {
            $create->id();
            $create->string('key')->unique();
            $create->text('value')->nullable();
            $create->string('group')->default('general'); // appearance, sidebar, branding, etc.
            $create->string('type')->default('text'); // text, color, file, boolean
            $create->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
