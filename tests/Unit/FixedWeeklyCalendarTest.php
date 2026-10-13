<?php

namespace Tests\Unit;

use App\Services\CalendarHelper;
use App\Models\WeeklyPerformance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedWeeklyCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_weeks_run_saturday_to_friday_and_belong_to_friday_month(): void
    {
        $september = CalendarHelper::getMonthWeeks('september', 2026);
        $this->assertSame('2026-08-29', $september[1]['start_full']);
        $this->assertSame('2026-09-04', $september[1]['end_full']);
        $this->assertSame('Sat', $september[1]['days'][1]['day_name']);
        $this->assertSame('Fri', $september[1]['days'][7]['day_name']);
        $this->assertSame('2026-09-07 17:00:00', $september[1]['due_at']);
    }

    public function test_august_does_not_claim_a_week_whose_friday_is_in_september(): void
    {
        $august = CalendarHelper::getMonthWeeks('august', 2026);
        $this->assertSame('2026-08-28', end($august)['end_full']);
    }

    public function test_late_submission_is_an_indicator_not_a_workflow_status(): void
    {
        $entry = new WeeklyPerformance([
            'workflow_status' => 'submitted_to_plan_report_department',
            'due_at' => '2026-09-07 17:00:00',
            'submitted_at' => '2026-09-09 09:00:00',
        ]);

        $this->assertTrue($entry->isLateSubmitted());
        $this->assertSame('submitted_to_plan_report_department', $entry->workflow_status);
    }
}
