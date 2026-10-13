<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PeriodicPerformance;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\Role;
use App\Models\StrategicObjective;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPerformanceComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_department_performer_comparison_is_restricted_to_own_department(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $own = Department::create(['name'=>'Own Operations','code'=>'OWN','reporting_type'=>'monthly','is_active'=>true]);
        $other = Department::create(['name'=>'Other Operations','code'=>'OTH','reporting_type'=>'monthly','is_active'=>true]);
        $user = User::factory()->create(['department_id'=>$own->id,'is_active'=>true]);
        $user->roles()->sync([Role::where('name','department_chief')->value('id')]);
        $this->performance($own, 2025, 60, $user, 'Own KPI');
        $this->performance($own, 2026, 80, $user, 'Own KPI');
        $this->performance($other, 2025, 100, $user, 'Other confidential KPI');
        $this->performance($other, 2026, 10, $user, 'Other confidential KPI');

        $response=$this->actingAs($user)->get(route('dashboard',['performance_year'=>2026]))->assertOk()->assertSee('YEAR-OVER-YEAR COMPARISON');
        $analytics=$response->viewData('performanceAnalytics');
        $this->assertFalse($analytics['organizationWide']);
        $this->assertSame(80.0,$analytics['comparison']['currentAverage']);
        $this->assertSame(60.0,$analytics['comparison']['previousAverage']);
        $this->assertSame(20.0,$analytics['comparison']['delta']);
        $this->assertNotContains('Other Operations',$analytics['comparison']['departments']['labels']->all());
    }

    public function test_plan_report_role_can_compare_departments_and_filter_one_department(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $first=Department::create(['name'=>'Operations A','code'=>'OPA','reporting_type'=>'monthly','is_active'=>true]);
        $second=Department::create(['name'=>'Operations B','code'=>'OPB','reporting_type'=>'monthly','is_active'=>true]);
        $user=User::factory()->create(['department_id'=>$first->id,'is_active'=>true]);
        $user->roles()->sync([Role::where('name','plan_report_department_expert')->value('id')]);
        $this->performance($first,2025,50,$user,'Availability');$this->performance($first,2026,75,$user,'Availability');
        $this->performance($second,2025,70,$user,'Punctuality');$this->performance($second,2026,90,$user,'Punctuality');

        $all=$this->actingAs($user)->get(route('dashboard',['performance_year'=>2026]))->assertOk()->viewData('performanceAnalytics');
        $this->assertTrue($all['organizationWide']);
        $this->assertContains('Operations A',$all['comparison']['departments']['labels']->all());
        $this->assertContains('Operations B',$all['comparison']['departments']['labels']->all());
        $filtered=$this->actingAs($user)->get(route('dashboard',['performance_year'=>2026,'performance_department'=>$first->id]))->assertOk()->viewData('performanceAnalytics');
        $this->assertSame(75.0,$filtered['comparison']['currentAverage']);
        $this->assertNotContains('Operations B',$filtered['comparison']['departments']['labels']->all());
        $this->actingAs($user)->get(route('performance.comparison',[
            'performance_year'=>2026,
            'performance_department'=>$first->id,
            'performance_period'=>'july',
            'performance_kpi'=>'Availability',
        ]))->assertOk()->assertSee('Performance Comparison')->assertSee('Same-Period Trend')->assertSee('Availability');
    }

    private function performance(Department $department, int $year, float $actual, User $creator, string $description): void
    {
        $objective=StrategicObjective::create(['code'=>$department->code.$year,'description'=>$description,'weight'=>100,'unit'=>'Percentage (%)','department_id'=>$department->id,'performers'=>[$department->id],'status'=>'approved','is_active'=>true]);
        $plan=Plan::create(['title'=>$department->name.' '.$year,'type'=>'annual','year'=>$year,'department_id'=>$department->id,'status'=>'approved','created_by'=>$creator->id]);
        $item=PlanItem::create(['plan_id'=>$plan->id,'strategic_objective_id'=>$objective->id,'annual_target'=>100,'july_target'=>100,'weight'=>100,'unit'=>'percent','performers'=>[$department->id]]);
        PeriodicPerformance::create(['plan_item_id'=>$item->id,'period'=>$year.'-july','actual_value'=>$actual,'status'=>'approved','workflow_status'=>'approved_by_head_office']);
    }
}
