<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->foreignId('recall_requested_by')->nullable()->after('rejection_comment')->constrained('users')->nullOnDelete();
            $table->timestamp('recall_requested_at')->nullable()->after('recall_requested_by');
            $table->text('recall_reason')->nullable()->after('recall_requested_at');
            $table->string('recall_from_status')->nullable()->after('recall_reason');
        });
    }

    public function down(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recall_requested_by');
            $table->dropColumn(['recall_requested_at', 'recall_reason', 'recall_from_status']);
        });
    }
};
