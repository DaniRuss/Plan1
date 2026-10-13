<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use App\Services\ObjectiveWorkbookImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ObjectiveWorkbookImportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_does_not_write_and_commit_creates_draft_hierarchy(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'system_admin']);
        $admin->roles()->attach($role);
        $department = Department::create(['name' => 'Planning', 'code' => 'PLN']);
        $contributor = Department::create(['name' => 'Operation', 'code' => 'OPS']);
        $token = (string) Str::uuid();
        $base = array_fill_keys(array_keys(ObjectiveWorkbookImport::PERIOD_COLUMNS), null);
        $rows = [
            array_merge($base, ['source_row' => 2, 'code' => '90', 'description' => 'Main', 'weight' => 1, 'unit' => '%', 'baseline_value' => null, 'annual_target' => 10, 'performer_text' => 'Planning']),
            array_merge($base, ['source_row' => 3, 'code' => '90.1', 'description' => 'Activity', 'weight' => 1, 'unit' => '%', 'baseline_value' => 0, 'annual_target' => 10, 'performer_text' => 'Operation, Planning', 'july_target' => 0]),
        ];
        Storage::disk('local')->put('objective-imports/'.$token.'.json', json_encode(['user_id' => $admin->id, 'target_year' => 2019, 'filename' => 'plan.xlsx', 'rows' => $rows]));

        $response = $this->actingAs($admin)->get(route('objectives.import.review', $token))->assertOk()->assertSee('Activity');
        $this->assertMatchesRegularExpression('/name="rows\[1\]\[department_id\]"[^>]*>.*?<option value="'.$contributor->id.'" selected/s', $response->getContent());
        $this->assertMatchesRegularExpression('/name="rows\[1\]\[performers\]\[\]"[^>]*>.*?<option value="'.$contributor->id.'" selected/s', $response->getContent());
        $this->assertDatabaseCount('strategic_objectives', 0);

        $input = ['rows' => []];
        foreach ($rows as $i => $row) {
            $input['rows'][$i] = ['code' => $row['code'], 'weight' => $row['weight'], 'department_id' => $department->id, 'performers' => [$department->id], 'goal_direction' => 'increase', 'weekly_plan_mode' => 'distribute', 'target_distribution' => 'checkpoint'];
        }
        $input['rows'][1]['department_id'] = $contributor->id;
        $input['rows'][1]['performers'] = [$contributor->id, $department->id];
        $input['rows'][1]['reporting_frequency'] = 'MONTHLY';
        $this->actingAs($admin)->post(route('objectives.import.commit', $token), $input)->assertRedirect(route('objectives.index'));
        $parent = StrategicObjective::where('code', '90')->firstOrFail();
        $child = StrategicObjective::where('code', '90.1')->firstOrFail();
        $this->assertSame($parent->id, $child->parent_id);
        $this->assertSame('draft', $child->status);
        $this->assertSame('monthly', $child->reporting_frequency);
        $this->assertSame($contributor->id, $child->lead_department_id);
        $this->assertSame([$contributor->id, $department->id], $child->performers);
        $this->assertSame('0.0000', $child->july_target);
    }

    public function test_blank_parent_weights_and_performers_roll_up_through_four_levels(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create();
        $role = Role::create(['name' => 'system_admin']);
        $admin->roles()->attach($role);
        $operation = Department::create(['name' => 'Operation', 'code' => 'OPS']);
        $finance = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $token = (string) Str::uuid();
        $base = array_fill_keys(array_keys(ObjectiveWorkbookImport::PERIOD_COLUMNS), null);
        $rows = [
            array_merge($base, ['source_row' => 2, 'code' => '91', 'description' => 'Main', 'weight' => null, 'unit' => '%', 'baseline_value' => null, 'annual_target' => null, 'performer_text' => '']),
            array_merge($base, ['source_row' => 3, 'code' => '91.1', 'description' => 'Sub objective', 'weight' => null, 'unit' => '%', 'baseline_value' => null, 'annual_target' => null, 'performer_text' => '']),
            array_merge($base, ['source_row' => 4, 'code' => '91.1.1', 'description' => 'First activity', 'weight' => .4, 'unit' => '%', 'baseline_value' => 0, 'annual_target' => 10, 'performer_text' => 'Operation']),
            array_merge($base, ['source_row' => 5, 'code' => '91.1.2', 'description' => 'Second activity', 'weight' => .6, 'unit' => '%', 'baseline_value' => 0, 'annual_target' => 10, 'performer_text' => 'Finance']),
            array_merge($base, ['source_row' => 6, 'code' => '91.1.3', 'description' => 'Unweighted breakdown', 'weight' => null, 'unit' => '%', 'baseline_value' => 0, 'annual_target' => 10, 'performer_text' => 'Finance']),
            array_merge($base, ['source_row' => 7, 'code' => '91.1.1.1', 'description' => 'Fourth-level detail', 'weight' => null, 'unit' => '%', 'baseline_value' => 0, 'annual_target' => 10, 'performer_text' => 'Finance']),
        ];
        Storage::disk('local')->put('objective-imports/'.$token.'.json', json_encode(['user_id' => $admin->id, 'target_year' => 2019, 'filename' => 'plan.xlsx', 'rows' => $rows]));

        $response = $this->actingAs($admin)->get(route('objectives.import.review', $token))->assertOk();
        $response->assertSee('Calculated from direct children')->assertSee('value="1"', false);

        $input = ['rows' => [
            0 => ['code' => '91', 'weight' => 1, 'goal_direction' => 'increase', 'weekly_plan_mode' => 'distribute', 'target_distribution' => 'checkpoint'],
            1 => ['code' => '91.1', 'weight' => 1, 'goal_direction' => 'increase', 'weekly_plan_mode' => 'distribute', 'target_distribution' => 'checkpoint'],
            2 => ['code' => '91.1.1', 'weight' => .4, 'department_id' => $operation->id, 'performers' => [$operation->id], 'goal_direction' => 'increase', 'weekly_plan_mode' => 'distribute', 'target_distribution' => 'checkpoint'],
            3 => ['code' => '91.1.2', 'weight' => .6, 'department_id' => $finance->id, 'performers' => [$finance->id], 'goal_direction' => 'increase', 'weekly_plan_mode' => 'distribute', 'target_distribution' => 'checkpoint'],
            4 => ['code' => '91.1.3', 'weight' => 0, 'department_id' => $finance->id, 'performers' => [$finance->id], 'goal_direction' => 'increase', 'weekly_plan_mode' => 'distribute', 'target_distribution' => 'checkpoint'],
            5 => ['code' => '91.1.1.1', 'weight' => 0, 'department_id' => $finance->id, 'performers' => [$finance->id], 'goal_direction' => 'increase', 'weekly_plan_mode' => 'distribute', 'target_distribution' => 'checkpoint'],
        ]];
        $this->actingAs($admin)->post(route('objectives.import.commit', $token), $input)->assertRedirect(route('objectives.index'));

        $main = StrategicObjective::where('code', '91')->firstOrFail();
        $sub = StrategicObjective::where('code', '91.1')->firstOrFail();
        $this->assertSame('1.00', $main->weight);
        $this->assertSame('1.00', $sub->weight);
        $this->assertTrue($main->is_calculated_weight);
        $this->assertTrue($sub->is_calculated_weight);
        $this->assertSame([$operation->id, $finance->id], $main->performers);
        $this->assertSame([$operation->id, $finance->id], $sub->performers);
        $this->assertSame($operation->id, $main->lead_department_id);
        $detail = StrategicObjective::where('code', '91.1.3')->firstOrFail();
        $this->assertTrue($detail->is_reference_only);
        $this->assertTrue($detail->exclude_from_overall_score);
        $this->assertFalse($detail->include_in_parent_rollup);
        $fourthLevel = StrategicObjective::where('code', '91.1.1.1')->firstOrFail();
        $this->assertSame(StrategicObjective::where('code', '91.1.1')->value('id'), $fourthLevel->parent_id);
        $this->assertTrue($fourthLevel->is_reference_only);
    }
}
