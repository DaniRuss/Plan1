<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->boolean('is_supplementary')->default(false)->after('is_reference_only');
            $table->boolean('exclude_from_overall_score')->default(false)->after('is_supplementary');
        });

        Schema::table('plan_item_assignments', function (Blueprint $table) {
            $table->foreignId('sent_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable()->after('sent_by');
            $table->foreignId('chief_reviewed_by')->nullable()->after('sent_at')->constrained('users')->nullOnDelete();
            $table->timestamp('chief_reviewed_at')->nullable()->after('chief_reviewed_by');
            $table->text('chief_note')->nullable()->after('chief_reviewed_at');
            $table->foreignId('assigned_directorate_user_id')->nullable()->after('chief_note')->constrained('users')->nullOnDelete();
            $table->foreignId('directorate_reviewed_by')->nullable()->after('assigned_directorate_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('directorate_reviewed_at')->nullable()->after('directorate_reviewed_by');
            $table->text('directorate_note')->nullable()->after('directorate_reviewed_at');
        });

        // Preserve all existing approved/reporting data. New publications use
        // the new Chief -> Directorate workflow; historical items start final.
        DB::table('plan_item_assignments')->update([
            'status' => 'approved_for_experts',
            'accepted_at' => DB::raw('COALESCE(accepted_at, CURRENT_TIMESTAMP)'),
        ]);

        $approvedItems = DB::table('plan_items')
            ->join('plans', 'plans.id', '=', 'plan_items.plan_id')
            ->where('plans.status', 'approved')
            ->select('plan_items.id as plan_item_id', 'plans.department_id as performer_id')
            ->get();

        foreach ($approvedItems as $item) {
            DB::table('plan_item_assignments')->updateOrInsert(
                ['plan_item_id' => $item->plan_item_id, 'performer_id' => $item->performer_id],
                ['status' => 'approved_for_experts', 'accepted_at' => now(), 'updated_at' => now(), 'created_at' => now()]
            );
        }

        Schema::table('plan_item_assignments', function (Blueprint $table) {
            $table->unique(['plan_item_id', 'performer_id'], 'plan_item_performer_unique');
        });
    }

    public function down(): void
    {
        Schema::table('plan_item_assignments', function (Blueprint $table) {
            $table->dropUnique('plan_item_performer_unique');
            $table->dropConstrainedForeignId('sent_by');
            $table->dropColumn('sent_at');
            $table->dropConstrainedForeignId('chief_reviewed_by');
            $table->dropColumn(['chief_reviewed_at', 'chief_note']);
            $table->dropConstrainedForeignId('assigned_directorate_user_id');
            $table->dropConstrainedForeignId('directorate_reviewed_by');
            $table->dropColumn(['directorate_reviewed_at', 'directorate_note']);
        });

        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->dropColumn(['is_supplementary', 'exclude_from_overall_score']);
        });
    }
};
