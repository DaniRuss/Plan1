<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->unsignedInteger('revision_number')->default(0)->after('status');
            $table->foreignId('last_edited_by')->nullable()->after('revision_number')->constrained('users')->nullOnDelete();
            $table->timestamp('last_edited_at')->nullable()->after('last_edited_by');
            $table->string('last_edited_stage', 80)->nullable()->after('last_edited_at');
            $table->text('last_change_reason')->nullable()->after('last_edited_stage');
        });
    }

    public function down(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_edited_by');
            $table->dropColumn(['revision_number', 'last_edited_at', 'last_edited_stage', 'last_change_reason']);
        });
    }
};

