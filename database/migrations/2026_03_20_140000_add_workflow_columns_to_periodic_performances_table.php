<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('periodic_performances')) {
            return;
        }

        Schema::table('periodic_performances', function (Blueprint $table) {
            if (!Schema::hasColumn('periodic_performances', 'workflow_status')) {
                $table->string('workflow_status')->nullable()->after('status');
            }
            if (!Schema::hasColumn('periodic_performances', 'workflow_note')) {
                $table->text('workflow_note')->nullable()->after('workflow_status');
            }
            if (!Schema::hasColumn('periodic_performances', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('workflow_note')->constrained('users');
            }
            if (!Schema::hasColumn('periodic_performances', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            }
            if (!Schema::hasColumn('periodic_performances', 'directorate_reviewed_by')) {
                $table->foreignId('directorate_reviewed_by')->nullable()->after('submitted_at')->constrained('users');
            }
            if (!Schema::hasColumn('periodic_performances', 'directorate_reviewed_at')) {
                $table->timestamp('directorate_reviewed_at')->nullable()->after('directorate_reviewed_by');
            }
            if (!Schema::hasColumn('periodic_performances', 'chief_reviewed_by')) {
                $table->foreignId('chief_reviewed_by')->nullable()->after('directorate_reviewed_at')->constrained('users');
            }
            if (!Schema::hasColumn('periodic_performances', 'chief_reviewed_at')) {
                $table->timestamp('chief_reviewed_at')->nullable()->after('chief_reviewed_by');
            }
            if (!Schema::hasColumn('periodic_performances', 'plan_report_reviewed_by')) {
                $table->foreignId('plan_report_reviewed_by')->nullable()->after('chief_reviewed_at')->constrained('users');
            }
            if (!Schema::hasColumn('periodic_performances', 'plan_report_reviewed_at')) {
                $table->timestamp('plan_report_reviewed_at')->nullable()->after('plan_report_reviewed_by');
            }
            if (!Schema::hasColumn('periodic_performances', 'head_office_reviewed_by')) {
                $table->foreignId('head_office_reviewed_by')->nullable()->after('plan_report_reviewed_at')->constrained('users');
            }
            if (!Schema::hasColumn('periodic_performances', 'head_office_reviewed_at')) {
                $table->timestamp('head_office_reviewed_at')->nullable()->after('head_office_reviewed_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('periodic_performances')) {
            return;
        }

        Schema::table('periodic_performances', function (Blueprint $table) {
            foreach ([
                'head_office_reviewed_at',
                'head_office_reviewed_by',
                'plan_report_reviewed_at',
                'plan_report_reviewed_by',
                'chief_reviewed_at',
                'chief_reviewed_by',
                'directorate_reviewed_at',
                'directorate_reviewed_by',
                'submitted_at',
                'submitted_by',
            ] as $column) {
                if (Schema::hasColumn('periodic_performances', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['workflow_note', 'workflow_status'] as $column) {
                if (Schema::hasColumn('periodic_performances', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
