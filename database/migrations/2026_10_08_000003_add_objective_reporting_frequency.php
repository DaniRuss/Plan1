<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->string('reporting_frequency')->nullable();
            $table->string('pending_reporting_frequency')->nullable();
            $table->text('frequency_change_reason')->nullable();
            $table->foreignId('frequency_requested_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }
    public function down(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->dropConstrainedForeignId('frequency_requested_by');
            $table->dropColumn(['reporting_frequency', 'pending_reporting_frequency', 'frequency_change_reason']);
        });
    }
};
