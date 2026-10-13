<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_performances', function (Blueprint $table) {
            if (!Schema::hasColumn('weekly_performances', 'workflow_status')) {
                $table->string('workflow_status', 50)->default('draft')->after('status');
            }
            if (!Schema::hasColumn('weekly_performances', 'workflow_note')) {
                $table->text('workflow_note')->nullable()->after('workflow_status');
            }
            if (!Schema::hasColumn('weekly_performances', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('workflow_note')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('weekly_performances', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
            if (!Schema::hasColumn('weekly_performances', 'directorate_reviewed_by')) {
                $table->foreignId('directorate_reviewed_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('weekly_performances', 'directorate_reviewed_at')) {
                $table->timestamp('directorate_reviewed_at')->nullable()->after('directorate_reviewed_by');
            }
            if (!Schema::hasColumn('weekly_performances', 'chief_reviewed_by')) {
                $table->foreignId('chief_reviewed_by')->nullable()->after('directorate_reviewed_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('weekly_performances', 'chief_reviewed_at')) {
                $table->timestamp('chief_reviewed_at')->nullable()->after('chief_reviewed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('weekly_performances', function (Blueprint $table) {
            foreach ([
                'submitted_by',
                'directorate_reviewed_by',
                'chief_reviewed_by',
            ] as $foreignColumn) {
                if (Schema::hasColumn('weekly_performances', $foreignColumn)) {
                    $table->dropForeign([$foreignColumn]);
                }
            }

            $dropColumns = [];
            foreach ([
                'workflow_status',
                'workflow_note',
                'submitted_by',
                'submitted_at',
                'directorate_reviewed_by',
                'directorate_reviewed_at',
                'chief_reviewed_by',
                'chief_reviewed_at',
            ] as $column) {
                if (Schema::hasColumn('weekly_performances', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if ($dropColumns) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
