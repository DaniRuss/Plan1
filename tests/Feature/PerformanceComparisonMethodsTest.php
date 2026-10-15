<?php

namespace Tests\Feature;

use App\Models\{Department, Plan, PlanItem, StrategicObjective, PeriodicPerformance, Role, User};
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceComparisonMethodsTest extends TestCase
{
    use RefreshDatabase;

    private function user(Department $department, string $role = 'plan_report_department_expert'): User
    {
        $user = User::factory()->create(['department_id'=>$department->id,'must_change_password'=>false]);
        $user->roles()->attach(Role::where('name',$role)->firstOrFail());
        return $user;
    }

    private function createPerformance(Department $department, User $user, int $year, string $month, float $actual, string $state = 'approved_by_head_office'): void
    {
        $objective = StrategicObjective::create(['department_id'=>$department->id,'code'=>$department->id.'.'.$year.'.'.$month,'description'=>'Cargo volume','unit'=>'Tons','weight'=>10,'status'=>'approved']);
        $plan = Plan::create(['department_id'=>$department->id,'year'=>$year,'title'=>'Annual plan','type'=>'annual','status'=>'approved','created_by'=>$user->id]);
        $item = PlanItem::create(['plan_id'=>$plan->id,'strategic_objective_id'=>$objective->id,$month.'_target'=>100]);
        PeriodicPerformance::create(['plan_item_id'=>$item->id,'period'=>"$year-$month",'actual_value'=>$actual,'status'=>'submitted','workflow_status'=>$state]);
    }

    public function test_selected_reference_year_and_period_comparisons(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name'=>'Cargo','code'=>'CG','reporting_type'=>'monthly']);
        $user = $this->user($department);
        $this->createPerformance($department,$user,2024,'july',50);
        $this->createPerformance($department,$user,2026,'july',80);
        $this->createPerformance($department,$user,2026,'august',90);
        $year = $this->actingAs($user)->get(route('performance.comparison',['performance_year'=>2026,'reference_year'=>2024,'performance_period'=>'july']))->assertOk()->viewData('comparison');
        $this->assertSame(30.0,$year['delta']);
        $period = $this->get(route('performance.comparison',['comparison_method'=>'period','performance_year'=>2026,'performance_period'=>'august','reference_period'=>'july']))->assertOk()->viewData('comparison');
        $this->assertSame(10.0,$period['delta']);
    }

    public function test_department_and_uncapped_target_comparisons(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $first = Department::create(['name'=>'Cargo A','code'=>'CA','reporting_type'=>'monthly']);
        $second = Department::create(['name'=>'Cargo B','code'=>'CB','reporting_type'=>'monthly']);
        $user = $this->user($first);
        $this->createPerformance($first,$user,2026,'july',120);
        $this->createPerformance($second,$user,2026,'july',60);
        $result = $this->actingAs($user)->get(route('performance.comparison',['comparison_method'=>'department','performance_year'=>2026,'performance_period'=>'july','performance_department'=>$first->id,'reference_department'=>$second->id]))->assertOk()->viewData('comparison');
        $this->assertSame(40.0,$result['delta']);
        $target = $this->get(route('performance.comparison',['comparison_method'=>'target','performance_year'=>2026,'performance_period'=>'july','performance_department'=>$first->id]))->assertOk()->viewData('comparison');
        $this->assertSame(120.0,$target['currentAverage']);
        $this->assertSame(20.0,$target['delta']);
    }

    public function test_provisional_results_missing_comparisons_and_department_scope(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $own = Department::create(['name'=>'Own','code'=>'OW','reporting_type'=>'monthly']);
        $other = Department::create(['name'=>'Other','code'=>'OT','reporting_type'=>'monthly']);
        $user = $this->user($own,'department_chief');
        $this->createPerformance($own,$user,2026,'july',70,'submitted_to_plan_report_department');
        $official = $this->actingAs($user)->get(route('performance.comparison'))->assertOk()->viewData('comparison');
        $this->assertCount(0,$official['rows']);
        $reported = $this->get(route('performance.comparison',['result_scope'=>'reported']))->assertOk()->assertSee('Provisional comparison.')->viewData('comparison');
        $this->assertCount(1,$reported['rows']);
        $this->assertNull($reported['rows'][0]['reference']);
        $this->assertNull($reported['rows'][0]['delta']);
        $this->get(route('performance.comparison',['performance_department'=>$other->id]))->assertSessionHasErrors('performance_department');
        $this->get(route('performance.comparison',['performance_period'=>'invalid']))->assertSessionHasErrors('performance_period');
    }

    public function test_year_comparison_does_not_pair_different_months(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name'=>'Cargo','code'=>'CG','reporting_type'=>'monthly']);
        $user = $this->user($department);
        $this->createPerformance($department,$user,2025,'july',50);
        $this->createPerformance($department,$user,2026,'august',90);
        $comparison = $this->actingAs($user)->get(route('performance.comparison',['performance_year'=>2026]))->assertOk()->viewData('comparison');
        $this->assertSame(0,$comparison['matched']);
        $this->assertSame(1,$comparison['unmatched']);
        $this->assertNull($comparison['delta']);
    }

    public function test_weekly_values_are_aggregated_before_monthly_target_comparison(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $department = Department::create(['name'=>'Cargo','code'=>'CG','reporting_type'=>'weekly']);
        $user = $this->user($department);
        $objective = StrategicObjective::create(['code'=>'1','department_id'=>$department->id,'description'=>'Cargo','weight'=>10,'unit'=>'Tons','status'=>'approved','target_distribution'=>'sum']);
        $plan = Plan::create(['department_id'=>$department->id,'year'=>2026,'title'=>'Cargo plan','type'=>'annual','status'=>'approved','created_by'=>$user->id]);
        $item = PlanItem::create(['plan_id'=>$plan->id,'strategic_objective_id'=>$objective->id,'july_target'=>100]);
        foreach ([40,80] as $index=>$actual) {
            \App\Models\WeeklyPerformance::create(['plan_item_id'=>$item->id,'year'=>2026,'month'=>'july','week_number'=>$index+1,'planned_value'=>50,'actual_value'=>$actual,'percentage'=>$actual*2,'workflow_status'=>'approved_by_head_office']);
        }
        $comparison = $this->actingAs($user)->get(route('performance.comparison',['comparison_method'=>'target','performance_year'=>2026,'performance_period'=>'july','reporting_frequency'=>'weekly']))->assertOk()->viewData('comparison');
        $this->assertSame(120.0,$comparison['currentAverage']);
        $this->assertSame(120.0,$comparison['rows'][0]['actual']);
    }
}
