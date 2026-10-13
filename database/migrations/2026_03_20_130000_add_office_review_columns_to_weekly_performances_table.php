<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_performances', function (Blueprint $table) {
            if (!Schema::hasColumn('weekly_performances', 'plan_report_reviewed_by')) {
                $table->foreignId('plan_report_reviewed_by')->nullable()->after('chief_reviewed_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('weekly_performances', 'plan_report_reviewed_at')) {
                $table->timestamp('plan_report_reviewed_at')->nullable()->after('plan_report_reviewed_by');
            }
            if (!Schema::hasColumn('weekly_performances', 'head_office_reviewed_by')) {
                $table->foreignId('head_office_reviewed_by')->nullable()->after('plan_report_reviewed_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('weekly_performances', 'head_office_reviewed_at')) {
                $table->timestamp('head_office_reviewed_at')->nullable()->after('head_office_reviewed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('weekly_performances', function (Blueprint $table) {
            foreach (['plan_report_reviewed_by', 'head_office_reviewed_by'] as $foreignColumn) {
                if (Schema::hasColumn('weekly_performances', $foreignColumn)) {
                    $table->dropForeign([$foreignColumn]);
                }
            }

            $dropColumns = [];
            foreach ([
                'plan_report_reviewed_by',
                'plan_report_reviewed_at',
                'head_office_reviewed_by',
                'head_office_reviewed_at',
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
