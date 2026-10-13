<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('weekly_performances', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('week_number');
            $table->date('period_end')->nullable()->after('period_start');
            $table->timestamp('due_at')->nullable()->after('period_end');
            $table->timestamp('approved_at')->nullable()->after('head_office_reviewed_at');
            $table->unique(['plan_item_id', 'year', 'month', 'week_number'], 'weekly_performance_period_unique');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_performances', function (Blueprint $table) {
            $table->dropUnique('weekly_performance_period_unique');
            $table->dropColumn(['period_start', 'period_end', 'due_at', 'approved_at']);
        });
    }
};
