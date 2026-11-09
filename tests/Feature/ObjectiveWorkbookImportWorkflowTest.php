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

    public function test_import_review_can_edit_month_and_quarter_targets_without_losing_blanks_or_zero(): void
    {
        Storage::fake('local');
        $admin=User::factory()->create(['is_active'=>true,'must_change_password'=>false]);
        $admin->roles()->attach(Role::create(['name'=>'system_admin']));
        $department=Department::create(['name'=>'Passenger','code'=>'PAX']);
        $token=(string)Str::uuid();
        $row=array_merge(array_fill_keys(array_keys(ObjectiveWorkbookImport::PERIOD_COLUMNS),null),['source_row'=>2,'code'=>'88','description'=>'Passengers','weight'=>1,'unit'=>'PAX in 1,000','baseline_value'=>1,'annual_target'=>100,'performer_text'=>'Passenger','july_target'=>10,'august_target'=>7,'september_target'=>8,'q1_target'=>25]);
        Storage::disk('local')->put('objective-imports/'.$token.'.json',json_encode(['user_id'=>$admin->id,'target_year'=>2026,'filename'=>'pax.xlsx','rows'=>[$row]]));
        $url=route('objectives.import.review',$token);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('Edit targets')->assertSee('data-target-field="july_target"',false);
        $input=['rows'=>[['code'=>'88','weight'=>1,'department_id'=>$department->id,'performers'=>[$department->id],'goal_direction'=>'increase','weekly_plan_mode'=>'distribute','target_distribution'=>'manual']]];
        $invalid=json_encode([0=>['july_target'=>-1,'q1_target'=>'25.1234']]);
        $this->from($url)->post(route('objectives.import.commit',$token),$input+['target_overrides'=>$invalid])->assertSessionHasErrors();
        $this->assertDatabaseCount('strategic_objectives',0);
        $this->assertSame($invalid,session()->getOldInput('target_overrides'));
        $this->get($url)->assertOk()->assertSee('value="-1"',false);
        $this->post(route('objectives.import.commit',$token),$input+['target_overrides'=>json_encode([0=>['annual_target'=>999]])])->assertSessionHasErrors();
        $this->post(route('objectives.import.commit',$token),$input+['target_overrides'=>json_encode([0=>['july_target'=>'0','august_target'=>null,'q1_target'=>'25.1234']])])->assertRedirect(route('objectives.index'))->assertSessionHasNoErrors();
        $objective=StrategicObjective::where('code','88')->firstOrFail();
        $this->assertSame('0.0000',$objective->july_target);
        $this->assertNull($objective->august_target);
        $this->assertSame('8.0000',$objective->september_target);
        $this->assertSame('25.1234',$objective->q1_target);
        $this->assertSame('PAX in 1,000',$objective->unit);
        $this->assertSame('draft',$objective->status);
    }

    public function test_repair_fills_only_missing_targets_and_can_be_rerun(): void
    {
        Storage::fake('local');
        $department=Department::create(['name'=>'Passenger','code'=>'PAX']);
        $objective=StrategicObjective::create(['code'=>'3.1','description'=>'Passengers','department_id'=>$department->id,'unit'=>'PAX in 1,000','target_year'=>2026,'july_target'=>null,'august_target'=>99,'data_source'=>'Workbook: plan.xlsx, row 2','status'=>'draft']);
        $book=new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet=$book->getActiveSheet();
        $sheet->fromArray(['S.No','KPIs'],null,'A1');
        $sheet->setCellValue('A2','3.1')->setCellValue('B2','Passengers')->setCellValue('D2','PAX in 1,000');
        $sheet->setCellValue('F2',510.5)->setCellValue('G2','=F2/12')->setCellValue('H2','=F2/12');
        $path=tempnam(sys_get_temp_dir(),'restore-pax-');
        try {
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($path);
            $this->artisan('objectives:restore-workbook-targets',['file'=>$path,'--year'=>2026])->assertSuccessful();
            $this->assertNull($objective->fresh()->july_target);
            $this->artisan('objectives:restore-workbook-targets',['file'=>$path,'--year'=>2026,'--apply'=>true])->assertSuccessful();
            $this->assertEqualsWithDelta(42.5417,(float)$objective->fresh()->july_target,0.0001);
            $this->assertEquals(99,$objective->fresh()->august_target);
            $this->assertSame('draft',$objective->fresh()->status);
            $this->assertCount(1,Storage::disk('local')->allFiles('database-backups'));
            $this->artisan('objectives:restore-workbook-targets',['file'=>$path,'--year'=>2026,'--apply'=>true])->expectsOutput('0 objectives repaired.')->assertSuccessful();
        } finally { unlink($path); $book->disconnectWorksheets(); }
    }

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
