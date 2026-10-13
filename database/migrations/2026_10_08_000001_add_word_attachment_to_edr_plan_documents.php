<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('edr_plan_documents', function (Blueprint $table) {
            $table->string('word_file_path')->nullable();
            $table->string('word_file_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('edr_plan_documents', fn (Blueprint $table) => $table->dropColumn(['word_file_path', 'word_file_name']));
    }
};
