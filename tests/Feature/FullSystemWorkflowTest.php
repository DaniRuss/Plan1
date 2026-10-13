<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PeriodicPerformance;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\PlanItemAssignment;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use App\Models\WeeklyPerformance;
use App\Services\ScoreCalculator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FullSystemWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const YEAR = 2019;
    private const MONTH = 'july';
    private const WEEK = 1;

    public function test_excel_based_objectives_complete_the_full_reporting_workflow_and_produce_final_scores(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $planning = $this->department('Planning and Reporting', 'PLN', 'monthly');
        $commercial = $this->department('Commercial', 'COM', 'weekly');
        $maintenance = $this->department('Infrastructure Maintenance', 'MNT', 'daily');
        $operations = $this->department('Operations', 'OPS', 'monthly');
        $admin = $this->userWithRole($planning, 'system_admin');

        // The hierarchy mirrors the action-plan workbook: objective -> KPI/sub-objective.
        $this->actingAs($admin)->post(route('objectives.store'), [
            'code' => 'T1',
            'description' => 'Improve railway transport and infrastructure performance',
            'weight' => 0,
            'unit' => 'N/A',
            'annual_target' => 0,
            'target_distribution' => 'manual',
            'target_year' => self::YEAR,
            'performers' => [$planning->id],
            'goal_direction' => 'increase',
            'is_reference_only' => 1,
            'is_calculated_weight' => 1,
        ])->assertRedirect(route('objectives.index'));
        $root = StrategicObjective::where('code', 'T1')->firstOrFail();

        $cargo = $this->createKpi($admin, $root, [
            'code' => 'T1.1',
            'description' => 'Transport import and export cargo',
            'weight' => 4,
            'unit' => 'Tons (in 1000)',
            'annual_target' => 1200,
            'target_distribution' => 'sum',
            'weekly_plan_mode' => 'repeat',
            'july_target' => 100,
            'august_target' => 100,
            'september_target' => 100,
            'performers' => [$commercial->id],
        ]);

        $track = $this->createKpi($admin, $root, [
            'code' => 'T1.2',
            'description' => 'Conduct preventive track maintenance and inspection',
            'weight' => 3,
            'unit' => 'Kilometer',
            'annual_target' => 120,
            'target_distribution' => 'sum',
            'july_target' => 12,
            'august_target' => 10,
            'september_target' => 8,
            'performers' => [$maintenance->id],
        ]);

        $availability = $this->createKpi($admin, $root, [
            'code' => 'T1.3',
            'description' => 'Improve infrastructure availability',
            'weight' => 3,
            'unit' => 'Percentage (%)',
            'baseline_value' => 92,
            'baseline_year' => '2018 EFY',
            'annual_target' => 96,
            'target_distribution' => 'checkpoint',
            'july_target' => 93,
            'august_target' => 93.5,
            'september_target' => 94,
            'performers' => [$operations->id],
        ]);

        $this->assertSame(10.0, (float) $root->fresh()->weight);
        $this->assertSame(300.0, (float) $cargo->q1_target);
        $this->assertSame(30.0, (float) $track->q1_target);
        $this->assertSame(94.0, (float) $availability->q1_target);

        $this->actingAs($admin)->post(route('objectives.submit', $root))->assertRedirect();
        $this->actingAs($admin)->post(route('objectives.approve', $root))->assertRedirect();
        $this->assertSame(4, StrategicObjective::where('status', 'approved')->count());

        $cargoItem = $this->publishedItem($commercial, $cargo);
        $trackItem = $this->publishedItem($maintenance, $track);
        $availabilityItem = $this->publishedItem($operations, $availability);

        // This test focuses on reporting and scoring. Complete the newly
        // introduced assignment-approval gate before entering reports.
        PlanItemAssignment::query()->update(['status' => 'approved_for_experts']);

        $cargoUsers = $this->workflowUsers($commercial);
        $trackUsers = $this->workflowUsers($maintenance);
        $availabilityUsers = $this->workflowUsers($operations);

        $this->actingAs($cargoUsers['expert'])->post(route('weekly.store'), [
            'year' => self::YEAR,
            'month' => self::MONTH,
            'week' => self::WEEK,
            'department_id' => $commercial->id,
            // Repeat mode must use the monthly target (100) even if a client
            // attempts to submit a different weekly plan.
            'entries' => [$cargo->id => ['plan_value' => 20, 'actual' => 80]],
        ])->assertRedirect();
        $this->moveWeeklyToFinalApproval($commercial, $cargoUsers);

        $this->actingAs($trackUsers['expert'])->post(route('daily.store'), [
            'year' => self::YEAR,
            'month' => self::MONTH,
            'week' => self::WEEK,
            'department_id' => $maintenance->id,
            'weekly_plans' => [$trackItem->id => 12],
            'actuals' => [$trackItem->id => [
                'monday' => 2, 'tuesday' => 2, 'wednesday' => 2, 'thursday' => 2, 'friday' => 2,
            ]],
        ])->assertRedirect();
        $this->moveWeeklyToFinalApproval($maintenance, $trackUsers);

        $this->actingAs($availabilityUsers['expert'])->post(
            route('monthly.entry.store', ['year' => self::YEAR, 'month' => self::MONTH]),
            [
                'department_id' => $operations->id,
                'entries' => [$availabilityItem->id => [
                    'actual_value' => 92.5,
                    'notes' => 'Sample checkpoint result from the action-plan scenario.',
                ]],
            ]
        )->assertRedirect(route('monthly.dashboard'));
        $this->moveMonthlyToFinalApproval($operations, $availabilityUsers);

        $this->assertDatabaseHas('weekly_performances', [
            'plan_item_id' => $cargoItem->id,
            'planned_value' => 100,
            'actual_value' => 80,
            'percentage' => 80,
            'workflow_status' => 'approved_by_head_office',
        ]);
        $this->assertDatabaseHas('weekly_performances', [
            'plan_item_id' => $trackItem->id,
            'actual_value' => 10,
            'workflow_status' => 'approved_by_head_office',
        ]);
        $this->assertDatabaseHas('periodic_performances', [
            'plan_item_id' => $availabilityItem->id,
            'period' => '2019-july',
            'actual_value' => 92.5,
            'status' => 'approved',
            'workflow_status' => 'approved_by_head_office',
        ]);

        $calculator = app(ScoreCalculator::class);
        $this->assertSame(80.0, $calculator->calculateDepartmentAverage($commercial, $cargoItem->plan, 'july'));
        $this->assertSame(83.33, $calculator->calculateDepartmentAverage($maintenance, $trackItem->plan, 'july'));
        $this->assertSame(50.0, $calculator->calculateDepartmentAverage($operations, $availabilityItem->plan, 'july'));
    }

    private function createKpi(User $admin, StrategicObjective $parent, array $overrides): StrategicObjective
    {
        $payload = array_merge([
            'parent_id' => $parent->id,
            'target_year' => self::YEAR,
            'goal_direction' => 'increase',
            'summary_type' => 'weighted',
            'july_target' => 0,
            'august_target' => 0,
            'september_target' => 0,
        ], $overrides);

        $this->actingAs($admin)->post(route('objectives.store'), $payload)
            ->assertRedirect(route('objectives.index'));

        return StrategicObjective::where('code', $payload['code'])->firstOrFail();
    }

    private function department(string $name, string $code, string $reportingType): Department
    {
        return Department::create([
            'name' => $name,
            'code' => $code,
            'reporting_type' => $reportingType,
            'objective_weight' => 100,
            'is_active' => true,
        ]);
    }

    private function userWithRole(Department $department, string $role): User
    {
        $user = User::factory()->create(['department_id' => $department->id, 'is_active' => true]);
        $user->roles()->sync([Role::where('name', $role)->value('id')]);
        return $user;
    }

    private function workflowUsers(Department $department): array
    {
        return [
            'expert' => $this->userWithRole($department, 'department_expert'),
            'directorate' => $this->userWithRole($department, 'department_directorate'),
            'chief' => $this->userWithRole($department, 'department_chief'),
            'plan_report' => $this->userWithRole($department, 'plan_report_department_expert'),
            'plan_report_directorate' => $this->userWithRole($department, 'plan_report_directorate'),
            'head_office' => $this->userWithRole($department, 'plan_report_head_office'),
        ];
    }

    private function publishedItem(Department $department, StrategicObjective $objective): PlanItem
    {
        $plan = Plan::where([
            'department_id' => $department->id,
            'year' => self::YEAR,
            'status' => 'approved',
        ])->firstOrFail();

        return $plan->items()->where('strategic_objective_id', $objective->id)->firstOrFail();
    }

    private function moveWeeklyToFinalApproval(Department $department, array $users): void
    {
        $this->transitionWeekly($users['expert'], $department, 'submit_to_directorate');
        $this->transitionWeekly($users['directorate'], $department, 'submit_to_chief');
        $this->transitionWeekly($users['chief'], $department, 'approve_by_chief');
        $this->transitionWeekly($users['plan_report'], $department, 'mark_reviewed_no_correction');
        $this->transitionWeekly($users['plan_report_directorate'], $department, 'submit_to_head_office');
        $this->transitionWeekly($users['head_office'], $department, 'approve_by_head_office');
    }

    private function transitionWeekly(User $user, Department $department, string $action): void
    {
        $this->actingAs($user)->post(route('weekly.workflow', [
            'year' => self::YEAR, 'month' => self::MONTH, 'week' => self::WEEK,
        ]), ['department_id' => $department->id, 'action' => $action])->assertRedirect();
    }

    private function moveMonthlyToFinalApproval(Department $department, array $users): void
    {
        $steps = [
            ['expert', 'submit_to_directorate'],
            ['directorate', 'submit_to_chief'],
            ['chief', 'approve_by_chief'],
            ['plan_report', 'mark_reviewed_no_correction'],
            ['plan_report_directorate', 'submit_to_head_office'],
            ['head_office', 'approve_by_head_office'],
        ];

        foreach ($steps as [$role, $action]) {
            $this->actingAs($users[$role])->post(route('monthly.workflow', [
                'year' => self::YEAR, 'month' => self::MONTH,
            ]), ['department_id' => $department->id, 'action' => $action])->assertRedirect();
        }
    }
}
