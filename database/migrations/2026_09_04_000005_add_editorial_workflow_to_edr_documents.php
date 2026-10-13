<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE edr_reports MODIFY status VARCHAR(60) NOT NULL DEFAULT 'draft'");
        }

        Schema::table('edr_plan_documents', function (Blueprint $table) {
            $table->string('status', 60)->default('draft')->after('prepared_by');
            $table->foreignId('directorate_reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('directorate_reviewed_at')->nullable()->after('directorate_reviewed_by');
            $table->foreignId('head_approved_by')->nullable()->after('directorate_reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('head_approved_at')->nullable()->after('head_approved_by');
        });

        Schema::table('edr_feedbacks', function (Blueprint $table) {
            $table->foreignId('directorate_reviewed_by')->nullable()->after('prepared_by')->constrained('users')->nullOnDelete();
            $table->timestamp('directorate_reviewed_at')->nullable()->after('directorate_reviewed_by');
            $table->foreignId('head_approved_by')->nullable()->after('directorate_reviewed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('head_approved_at')->nullable()->after('head_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('edr_feedbacks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('directorate_reviewed_by');
            $table->dropConstrainedForeignId('head_approved_by');
            $table->dropColumn(['directorate_reviewed_at', 'head_approved_at']);
        });
        Schema::table('edr_plan_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('directorate_reviewed_by');
            $table->dropConstrainedForeignId('head_approved_by');
            $table->dropColumn(['status', 'directorate_reviewed_at', 'head_approved_at']);
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE edr_reports MODIFY status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft'");
        }
    }
};
