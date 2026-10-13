<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('strategic_objectives', 'owner_id')) {
            Schema::table('strategic_objectives', function (Blueprint $table) {
                $table->foreignId('owner_id')->nullable()->after('department_id')
                    ->constrained('users')->nullOnDelete();
            });
        }

        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->string('status', 80)->default('draft')->change();
        });

        // Repair objectives created under the faulty approved default after the
        // review workflow was introduced. Published objectives have plan items
        // and must retain their approved state.
        DB::table('strategic_objectives')
            ->where('status', 'approved')
            ->where('created_at', '>=', '2026-08-26 00:00:00')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('plan_items')
                    ->whereColumn('plan_items.strategic_objective_id', 'strategic_objectives.id');
            })
            ->update(['status' => 'draft', 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('strategic_objectives', function (Blueprint $table) {
            $table->string('status', 80)->default('approved')->change();
        });
    }
};
