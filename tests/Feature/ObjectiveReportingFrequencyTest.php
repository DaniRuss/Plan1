<?php

namespace Tests\Feature;

use App\Http\Controllers\DailyPerformanceController;
use App\Http\Controllers\MonthlyAggregationController;
use App\Http\Controllers\WeeklyPerformanceController;
use App\Models\Department;
use App\Models\PeriodicPerformance;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\PlanItemAssignment;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use App\Models\WeeklyPerformance;
use App\Services\ReportCompleteness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObjectiveReportingFrequencyTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $department = Department::create(['name' => 'Cargo', 'code' => 'CG', 'reporting_type' => 'weekly']);
        $admin = User::factory()->create(['department_id' => $department->id]);
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'system_admin']));
        $plan = Plan::create(['title' => 'Cargo', 'type' => 'annual', 'year' => 2018, 'department_id' => $department->id, 'status' => 'approved', 'created_by' => $admin->id]);
        $items = [];
        foreach (['weekly' => null, 'monthly' => 'monthly', 'daily' => 'daily'] as $key => $frequency) {
            $objective = StrategicObjective::create([
                'code' => (string) (count($items) + 1), 'description' => ucfirst($key) . ' cargo item',
                'unit' => 'Tons', 'weight' => 1, 'department_id' => $department->id,
                'status' => 'approved', 'reporting_frequency' => $frequency, 'target_distribution' => 'sum',
            ]);
            $items[$key] = PlanItem::create(['plan_id' => $plan->id, 'strategic_objective_id' => $objective->id, 'july_target' => 100]);
            PlanItemAssignment::create(['plan_item_id' => $items[$key]->id, 'performer_id' => $department->id, 'status' => 'approved_for_experts']);
        }
        $this->actingAs($admin);
        return [$department, $admin, $plan, $items];
    }

    public function test_effective_frequency_and_each_entry_query_use_the_same_rule(): void
    {
        [$department, $admin, $plan, $items] = $this->fixture();
        foreach ($items as $frequency => $item) {
            $this->assertSame($frequency, $item->effectiveReportingFrequency());
            $this->assertSame([$item->id], PlanItem::forReportingFrequency($frequency)->pluck('id')->all());
            $this->assertTrue($department->hasReportingFrequency($frequency));
        }
        foreach ([WeeklyPerformanceController::class => 'weekly', DailyPerformanceController::class => 'daily'] as $controller => $frequency) {
            $method = new \ReflectionMethod($controller, 'accessiblePlanItemsQuery');
            $query = $method->invoke(app($controller), $department->id);
            $this->assertSame([$items[$frequency]->id], $query->pluck('id')->all());
        }
        $this->get(route('monthly.entry.form', ['year' => 2018, 'month' => 'july', 'department_id' => $department->id]))
            ->assertOk()->assertSee('Monthly cargo item')->assertDontSee('Weekly cargo item')->assertDontSee('Daily cargo item');
        $this->get(route('weekly.entry', ['year' => 2018, 'month' => 'july', 'week' => 1, 'department_id' => $department->id]))
            ->assertOk()->assertSee('WEEKLY CARGO ITEM')->assertDontSee('MONTHLY CARGO ITEM')->assertDontSee('DAILY CARGO ITEM');
        $this->get(route('weekly.report', ['year' => 2018, 'month' => 'july', 'week' => 1, 'department_id' => $department->id]))
            ->assertOk()->assertSee('Weekly cargo item')->assertDontSee('Monthly cargo item')->assertDontSee('Daily cargo item');
        $this->post(route('monthly.entry.store', ['year' => 2018, 'month' => 'july']), [
            'department_id' => $department->id,
            'entries' => [$items['monthly']->id => ['actual_value' => 45], $items['weekly']->id => ['actual_value' => 999]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('periodic_performances', ['plan_item_id' => $items['monthly']->id, 'actual_value' => 45]);
        $this->assertDatabaseMissing('periodic_performances', ['plan_item_id' => $items['weekly']->id]);
    }

    public function test_monthly_override_ignores_old_weekly_data_and_contributes_to_quarter_and_year(): void
    {
        [$department, $admin, $plan, $items] = $this->fixture();
        $monthly = $items['monthly'];
        WeeklyPerformance::create(['plan_item_id' => $monthly->id, 'year' => 2018, 'month' => 'july', 'week_number' => 1, 'actual_value' => 999, 'planned_value' => 100, 'workflow_status' => 'approved_by_head_office']);
        PeriodicPerformance::create(['plan_item_id' => $monthly->id, 'period' => '2018-july', 'actual_value' => 45, 'workflow_status' => 'approved_by_head_office']);
        $this->assertEquals(45, $monthly->getActualValueForPeriod('july'));
        $this->assertEquals(45, $monthly->getActualValueForPeriod('q1'));
        $this->assertEquals(45, $monthly->getActualValueForPeriod('annual'));
        $this->assertTrue($monthly->hasPerformanceForPeriod('q1'));
        $weekly = app(ReportCompleteness::class)->forPlan($plan->fresh(), 'week:july:1');
        $this->assertSame(0, $weekly['approved']);
        $this->assertSame(1, $weekly['expected']);
    }

    public function test_downloaded_template_round_trips_through_the_import_parser(): void
    {
        $this->fixture();
        $response = $this->get(route('objectives.download-template'))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'frequency-template-') . '.xlsx';
        try {
            file_put_contents($path, $response->streamedContent());
            $rows = app(\App\Services\ObjectiveWorkbookImport::class)->parse($path);
            $this->assertCount(3, $rows);
            $this->assertNull($rows[0]['reporting_frequency']);
            $this->assertSame('monthly', $rows[2]['reporting_frequency']);
        } finally {
            unlink($path);
        }
    }

    public function test_monthly_only_items_do_not_generate_weekly_overdue_warnings(): void
    {
        [$department, $admin, $plan, $items] = $this->fixture();
        foreach ($items as $item) $item->strategicObjective->update(['reporting_frequency' => 'monthly']);
        $this->get(route('weekly.dashboard', ['year' => 2018, 'department_id' => $department->id]))
            ->assertOk()->assertViewHas('timeliness', fn ($timeliness) => $timeliness['missing'] === 0 && $timeliness['overdue'] === 0);
        $coverage = app(ReportCompleteness::class)->forPlan($plan->fresh(), 'week:july:1');
        $this->assertSame(0, $coverage['expected']);
    }

    public function test_approved_frequency_changes_require_reason_and_reapproval_and_are_audited(): void
    {
        [$department, $admin, $plan, $items] = $this->fixture();
        $objective = $items['weekly']->strategicObjective;
        $ordinary = User::factory()->create(['department_id' => $department->id]);
        $this->actingAs($ordinary)->post(route('objectives.frequency.request', $objective), ['reporting_frequency' => 'monthly', 'change_reason' => 'Monthly measure'])->assertForbidden();
        $expert = User::factory()->create(['department_id' => $department->id]);
        $expert->roles()->attach(Role::firstOrCreate(['name' => User::ROLE_PLAN_REPORT_DEPARTMENT_EXPERT]));
        $this->actingAs($expert)->post(route('objectives.frequency.request', $objective), ['reporting_frequency' => 'monthly'])->assertSessionHasErrors('change_reason');
        $this->post(route('objectives.frequency.request', $objective), ['reporting_frequency' => 'monthly', 'change_reason' => 'This cargo is measured monthly'])->assertRedirect();
        $this->assertNull($objective->fresh()->reporting_frequency);
        $this->assertSame('monthly', $objective->fresh()->pending_reporting_frequency);
        $this->post(route('objectives.frequency.decide', $objective), ['decision' => 'approve'])->assertForbidden();
        $this->actingAs($admin)->post(route('objectives.frequency.decide', $objective), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('monthly', $objective->fresh()->reporting_frequency);
        $this->assertNull($objective->fresh()->pending_reporting_frequency);
        $logs = \App\Models\AuditLog::where('action', 'strategic_objective_updated')->get();
        $this->assertCount(2, $logs);
        $details = json_decode($logs->last()->details, true);
        $this->assertSame('inherit', $details['changes']['reporting_frequency']['before']);
        $this->assertSame('monthly', $details['changes']['reporting_frequency']['after']);
        $this->assertEquals($department->id, $details['editor']['department_id']);
    }
}
