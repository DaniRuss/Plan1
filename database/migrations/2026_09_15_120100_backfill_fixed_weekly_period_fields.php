<?php

use App\Services\CalendarHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('weekly_performances')->orderBy('id')->chunkById(250, function ($rows) {
            foreach ($rows as $row) {
                $period = CalendarHelper::getMonthWeeks((string) $row->month, (int) $row->year)[(int) $row->week_number] ?? null;
                if (!$period) continue;
                DB::table('weekly_performances')->where('id', $row->id)->update([
                    'period_start' => $row->period_start ?: $period['start_full'],
                    'period_end' => $row->period_end ?: $period['end_full'],
                    'due_at' => $row->due_at ?: $period['due_at'],
                    'approved_at' => $row->approved_at ?: ($row->workflow_status === 'approved_by_head_office' ? $row->head_office_reviewed_at : null),
                ]);
            }
        });
    }

    public function down(): void {}
};
