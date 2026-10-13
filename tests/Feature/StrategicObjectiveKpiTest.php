<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategicObjectiveKpiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed roles if necessary, or just create a user with enough permissions
        Role::create(['name' => 'system_admin']);
    }

    public function test_objective_index_uses_natural_hierarchical_code_order(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'system_admin')->first());
        $department = Department::create(['name' => 'Planning', 'code' => 'PLN']);
        $admin->update(['department_id' => $department->id]);

        $makeObjective = fn (string $code, string $description, ?int $parentId = null) => StrategicObjective::create([
            'code' => $code,
            'description' => $description,
            'weight' => 10,
            'unit' => 'Count',
            'department_id' => $department->id,
            'parent_id' => $parentId,
            'performers' => [$department->id],
            'goal_direction' => 'increase',
            'is_active' => true,
        ]);

        $makeObjective('10', 'Root Ten');
        $makeObjective('2', 'Root Two');
        $rootOne = $makeObjective('1', 'Root One');
        $makeObjective('1.10', 'Child Ten', $rootOne->id);
        $makeObjective('1.2', 'Child Two', $rootOne->id);
        $makeObjective('1.1', 'Child One', $rootOne->id);

        $this->actingAs($admin)->get(route('objectives.index'))
            ->assertOk()
            ->assertSeeInOrder(['Root One', 'Child One', 'Child Two', 'Child Ten', 'Root Two', 'Root Ten']);
    }

    public function test_can_store_strategic_objective_with_kpi_code()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'system_admin')->first());
        $department = Department::create(['name' => 'Planning', 'code' => 'PLN']);
        $admin->update(['department_id' => $department->id]);

        $response = $this->actingAs($admin)->post(route('objectives.store'), [
            'code' => 'TEST-001',
            'kpi_code' => 'K1',
            'description' => 'Test KPI Objective',
            'weight' => 10,
            'unit' => 'Percentage (%)',
            'annual_target' => 95,
            'lead_department_id' => $department->id,
            'goal_direction' => 'increase',
            'target_year' => 2019,
        ]);

        $response->assertRedirect(route('objectives.index'));
        $this->assertDatabaseHas('strategic_objectives', [
            'code' => 'TEST-001',
            'kpi_code' => 'K1',
            'annual_target' => 95,
            'status' => 'draft',
            'owner_id' => $admin->id,
        ]);

        $objective = StrategicObjective::where('code', 'TEST-001')->firstOrFail();
        $this->assertSame($department->id, (int) $objective->lead_department_id);
        $this->assertSame([$department->id], $objective->performers);
        $this->actingAs($admin)->get(route('objectives.index'))
            ->assertOk()
            ->assertSee(route('objectives.create', ['parent_id' => $objective->id]), false)
            ->assertSee('Add Sub-Objective');
    }

    public function test_create_form_shows_kpi_code_and_sub_objective_can_store_it(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'system_admin')->first());
        $department = Department::create(['name' => 'Planning Forms', 'code' => 'PLF']);
        $admin->update(['department_id' => $department->id]);

        $parent = StrategicObjective::create([
            'code' => '1',
            'description' => 'Main objective',
            'weight' => 10,
            'unit' => 'Percentage (%)',
            'department_id' => $department->id,
            'performers' => [$department->id],
            'goal_direction' => 'increase',
        ]);

        $this->actingAs($admin)->get(route('objectives.create'))
            ->assertOk()
            ->assertSee('KPI Code (Optional)')
            ->assertSee('name="kpi_code"', false);

        $this->actingAs($admin)->post(route('objectives.store'), [
            'code' => '1.1',
            'kpi_code' => 'K2',
            'description' => 'Child KPI objective',
            'parent_id' => $parent->id,
            'weight' => 5,
            'unit' => 'Percentage (%)',
            'performers' => [$department->id],
            'goal_direction' => 'increase',
            'target_year' => 2019,
        ])->assertRedirect(route('objectives.index'));

        $this->assertDatabaseHas('strategic_objectives', [
            'code' => '1.1',
            'parent_id' => $parent->id,
            'kpi_code' => 'K2',
        ]);
    }

    public function test_child_creation_inherits_parent_performers_and_lead_but_keeps_them_editable(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'system_admin')->first());
        $lead = Department::create(['name' => 'Operation Parent', 'code' => 'OPP']);
        $contributor = Department::create(['name' => 'Finance Parent', 'code' => 'FNP']);
        $admin->update(['department_id' => $lead->id]);
        $parent = StrategicObjective::create([
            'code' => '88', 'description' => 'Parent objective', 'weight' => 10,
            'unit' => 'Number', 'department_id' => $lead->id,
            'lead_department_id' => $lead->id, 'performers' => [$lead->id, $contributor->id],
            'goal_direction' => 'increase', 'target_year' => 2019,
        ]);

        $response = $this->actingAs($admin)->get(route('objectives.create', ['parent_id' => $parent->id]))->assertOk();
        $response->assertSee('value="'.$lead->id.'" selected', false)
            ->assertSee('id="performer_'.$lead->id.'" value="'.$lead->id.'" checked', false)
            ->assertSee('id="performer_'.$contributor->id.'" value="'.$contributor->id.'" checked', false);

        $this->actingAs($admin)->post(route('objectives.store'), [
            'code' => '88.1', 'description' => 'Inherited child', 'parent_id' => $parent->id,
            'weight' => 5, 'unit' => 'Number', 'goal_direction' => 'increase', 'target_year' => 2019,
        ])->assertRedirect(route('objectives.index'));

        $child = StrategicObjective::where('code', '88.1')->firstOrFail();
        $this->assertSame($lead->id, (int) $child->lead_department_id);
        $this->assertSame([$lead->id, $contributor->id], $child->performers);
    }

    public function test_can_update_strategic_objective_kpi_code_and_target()
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'system_admin')->first());
        $department = Department::create(['name' => 'Planning', 'code' => 'PLN']);
        $admin->update(['department_id' => $department->id]);

        $objective = StrategicObjective::create([
            'code' => 'TEST-002',
            'description' => 'Old Description',
            'weight' => 5,
            'unit' => 'Count',
            'department_id' => $department->id,
            'performers' => [$department->id],
            'goal_direction' => 'increase',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('objectives.update', $objective->id), [
            'code' => 'TEST-002',
            'kpi_code' => 'K2',
            'description' => 'Updated Description',
            'weight' => 5,
            'unit' => 'Count',
            'annual_target' => 88,
            'performers' => [$department->id],
            'goal_direction' => 'increase',
            'is_active' => 1,
            'target_year' => 2019,
        ]);

        $response->assertRedirect(route('objectives.index'));
        $this->assertDatabaseHas('strategic_objectives', [
            'id' => $objective->id,
            'kpi_code' => 'K2',
            'annual_target' => 88,
        ]);
    }

    public function test_can_store_kilometer_objective_and_calculate_increasing_progress(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'system_admin')->first());
        $department = Department::create(['name' => 'Infrastructure', 'code' => 'INF']);
        $admin->update(['department_id' => $department->id]);

        $response = $this->actingAs($admin)->post(route('objectives.store'), [
            'code' => 'KM-001',
            'description' => 'Track maintained',
            'weight' => 10,
            'unit' => 'Kilometer',
            'annual_target' => 100,
            'performers' => [$department->id],
            'goal_direction' => 'increase',
            'target_year' => 2019,
        ]);

        $response->assertRedirect(route('objectives.index'));

        $objective = StrategicObjective::where('code', 'KM-001')->firstOrFail();
        $this->assertSame('Kilometer', $objective->unit);
        $this->assertSame(75.0, $objective->calculateProgress(75, 100, 0));
    }

    public function test_kilometer_progress_respects_baseline_and_decreasing_direction(): void
    {
        $objective = new StrategicObjective([
            'unit' => 'Kilometer',
            'goal_direction' => 'decrease',
            'baseline_value' => 200,
        ]);

        // Reducing a distance from 200 km to 150 km is half-way to a 100 km target.
        $this->assertSame(50.0, $objective->calculateProgress(150, 100));
    }

    public function test_calculated_parent_weight_and_three_level_action_plan_hierarchy(): void
    {
        $department = Department::create(['name' => 'Operations', 'code' => 'OPS']);
        $parent = StrategicObjective::create([
            'code' => '1', 'description' => 'Objective 1', 'weight' => 0, 'unit' => 'Tons (in 1000)',
            'department_id' => $department->id, 'performers' => [$department->id],
            'goal_direction' => 'increase', 'is_calculated_weight' => true,
        ]);
        $kpi = StrategicObjective::create([
            'code' => '1.1', 'description' => 'Import cargo', 'weight' => 3, 'unit' => 'Tons (in 1000)',
            'department_id' => $department->id, 'parent_id' => $parent->id,
            'performers' => [$department->id], 'goal_direction' => 'increase',
        ]);
        StrategicObjective::create([
            'code' => '1.1.1', 'description' => 'Multimodal cargo', 'weight' => 1, 'unit' => 'Tons (in 1000)',
            'department_id' => $department->id, 'parent_id' => $kpi->id,
            'performers' => [$department->id], 'goal_direction' => 'increase',
        ]);

        $parent->syncCalculatedWeight();

        $this->assertSame(3.0, (float) $parent->fresh()->weight);
        $this->assertSame(3, $kpi->children()->first()->getHierarchyLevel());
    }

    public function test_calculated_parent_can_be_created_without_weight_and_accumulates_child_weights(): void
    {
        $department = Department::create(['name' => 'Planning Auto Weight', 'code' => 'PAW']);
        $admin = User::factory()->create(['department_id' => $department->id]);
        $admin->roles()->attach(Role::where('name', 'system_admin')->first());

        $base = [
            'unit' => 'Count', 'target_year' => 2019,
            'performers' => [$department->id], 'goal_direction' => 'increase',
        ];

        $this->actingAs($admin)->post(route('objectives.store'), array_merge($base, [
            'code' => 'AUTO-1', 'description' => 'Calculated parent',
            'is_calculated_weight' => 1,
        ]))->assertRedirect(route('objectives.index'));

        $parent = StrategicObjective::where('code', 'AUTO-1')->firstOrFail();
        $this->assertSame(0.0, (float) $parent->weight);

        foreach ([['AUTO-1.1', 4.25], ['AUTO-1.2', 2.75]] as [$code, $weight]) {
            $this->actingAs($admin)->post(route('objectives.store'), array_merge($base, [
                'code' => $code, 'description' => "Child {$code}",
                'parent_id' => $parent->id, 'weight' => $weight,
            ]))->assertRedirect(route('objectives.index'));
        }

        $this->assertSame(7.0, (float) $parent->fresh()->weight);
        $this->assertSame(7.0, $parent->getAllocatedWeight());
    }

    public function test_objective_stores_official_july_to_june_target_schedule(): void
    {
        $department = Department::create(['name' => 'Commercial', 'code' => 'COM']);
        $objective = StrategicObjective::create([
            'code' => '2.1', 'description' => 'Transport cargo', 'weight' => 2,
            'unit' => 'Tons (in 1000)', 'department_id' => $department->id,
            'performers' => [$department->id], 'goal_direction' => 'increase',
            'annual_target' => 1200, 'target_distribution' => 'sum',
            'july_target' => 100, 'august_target' => 100, 'september_target' => 100, 'q1_target' => 300,
        ]);

        $this->assertSame('sum', $objective->target_distribution);
        $this->assertSame(100.0, (float) $objective->july_target);
        $this->assertSame(300.0, (float) $objective->q1_target);
    }

    public function test_checkpoint_quarters_are_calculated_from_each_final_month_on_the_server(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('name', 'system_admin')->first());
        $department = Department::create(['name' => 'Digital', 'code' => 'DIG']);
        $admin->update(['department_id' => $department->id]);

        $this->actingAs($admin)->post(route('objectives.store'), [
            'code' => '15.1', 'description' => 'ERP implementation', 'weight' => 1,
            'unit' => 'Percentage (%)', 'performers' => [$department->id],
            'goal_direction' => 'increase', 'target_distribution' => 'checkpoint',
            'target_year' => 2019,
            'july_target' => 5, 'august_target' => 10, 'september_target' => 15,
            'october_target' => 20, 'november_target' => 25, 'december_target' => 30,
            'january_target' => 40, 'february_target' => 50, 'march_target' => 60,
            'april_target' => 75, 'may_target' => 85, 'june_target' => 100,
        ])->assertRedirect(route('objectives.index'));

        $objective = StrategicObjective::where('code', '15.1')->firstOrFail();
        $this->assertSame(15.0, (float) $objective->q1_target);
        $this->assertSame(30.0, (float) $objective->q2_target);
        $this->assertSame(60.0, (float) $objective->q3_target);
        $this->assertSame(100.0, (float) $objective->q4_target);
    }

    public function test_plan_creation_preserves_cross_department_performers_and_assignments(): void
    {
        $planning = Department::create(['name' => 'Planning', 'code' => 'PLN']);
        $operation = Department::create(['name' => 'Operation', 'code' => 'OPS']);
        $admin = User::factory()->create(['department_id' => $planning->id]);
        $admin->roles()->attach(Role::where('name', 'system_admin')->first());
        $objective = StrategicObjective::create([
            'code' => '10.6', 'description' => 'Inspect bridge infrastructure', 'weight' => 0.1,
            'unit' => 'Set', 'department_id' => $planning->id, 'performers' => [$operation->id],
            'annual_target' => 4.87, 'goal_direction' => 'increase', 'status' => 'approved',
        ]);

        $this->actingAs($admin)->post(route('plans.store'), [
            'year' => 2019, 'department_id' => $planning->id,
            'items' => [$objective->id => [
                'annual_target' => 4.87,
                'performers' => [$operation->id],
                'july' => 1.22,
            ]],
        ])->assertRedirect(route('plans.index'));

        $item = \App\Models\PlanItem::where('strategic_objective_id', $objective->id)->firstOrFail();
        $this->assertSame([$operation->id], $item->performers);
        $this->assertDatabaseHas('plan_item_assignments', [
            'plan_item_id' => $item->id, 'performer_id' => $operation->id, 'status' => 'pending',
        ]);
    }

    public function test_objective_approval_publishes_directly_to_every_performer_reporting_type(): void
    {
        $owner = Department::create(['name' => 'Planning', 'code' => 'PLN', 'reporting_type' => 'monthly']);
        $daily = Department::create(['name' => 'Operation', 'code' => 'OPS', 'reporting_type' => 'daily']);
        $weekly = Department::create(['name' => 'Maintenance', 'code' => 'MNT', 'reporting_type' => 'weekly']);
        $monthly = Department::create(['name' => 'Commercial', 'code' => 'COM', 'reporting_type' => 'monthly']);
        $admin = User::factory()->create(['department_id' => $owner->id]);
        $admin->roles()->attach(Role::where('name', 'system_admin')->first());
        $objective = StrategicObjective::create([
            'code' => '20', 'description' => 'Shared company objective', 'weight' => 10,
            'unit' => 'Percentage (%)', 'department_id' => $owner->id,
            'performers' => [$daily->id, $weekly->id, $monthly->id],
            'annual_target' => 100, 'july_target' => 10, 'q1_target' => 30,
            'target_year' => 2019, 'goal_direction' => 'increase', 'status' => 'submitted',
        ]);

        $this->actingAs($admin)->post(route('objectives.approve', $objective))->assertRedirect();

        $this->assertSame('approved', $objective->fresh()->status);
        foreach ([$daily, $weekly, $monthly] as $performer) {
            $plan = \App\Models\Plan::where([
                'department_id' => $performer->id, 'year' => 2019, 'status' => 'approved',
            ])->firstOrFail();
            $this->assertDatabaseHas('plan_items', [
                'plan_id' => $plan->id, 'strategic_objective_id' => $objective->id,
                'annual_target' => 100,
            ]);
        }
    }
}
