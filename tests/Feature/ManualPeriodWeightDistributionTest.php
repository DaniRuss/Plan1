<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PeriodWeightDistribution;
use App\Models\Plan;
use App\Models\PlanItem;
use App\Models\Setting;
use App\Models\StrategicObjective;
use App\Models\User;
use App\Services\PeriodWeightNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualPeriodWeightDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_mode_requires_approved_weights_and_preserves_zero_targets(): void
    {
        Setting::set('period_weight_distribution_mode','manual','workflow');
        Setting::set('manual_period_weight_effective_from_year','2026','workflow');
        $department=Department::create(['name'=>'Manual Weights','code'=>'MW','reporting_type'=>'monthly','is_active'=>true]);
        $user=User::factory()->create(['department_id'=>$department->id]);
        $parent=StrategicObjective::create(['code'=>'1','description'=>'Parent','weight'=>1,'unit'=>'Number','department_id'=>$department->id,'status'=>'approved','is_active'=>true]);
        $plan=Plan::create(['title'=>'Manual plan','type'=>'annual','year'=>2026,'department_id'=>$department->id,'status'=>'approved','created_by'=>$user->id]);
        $definitions=[[.5,10],[.3,null],[.1,0],[.1,null]]; $items=collect();
        foreach($definitions as $index=>[$weight,$target]){
            $objective=StrategicObjective::create(['parent_id'=>$parent->id,'code'=>'1.'.($index+1),'description'=>'Activity '.($index+1),'weight'=>$weight,'unit'=>'Number','department_id'=>$department->id,'status'=>'approved','is_active'=>true]);
            $items->push(PlanItem::create(['plan_id'=>$plan->id,'strategic_objective_id'=>$objective->id,'annual_target'=>10,'july_target'=>$target,'weight'=>$weight,'unit'=>'number']));
        }
        $plan->load(['items.strategicObjective']);
        $resolver=app(PeriodWeightNormalizer::class);
        $missing=$resolver->forPeriod($plan->items,'july');
        $this->assertNull($missing[$items[0]->id]);
        $this->assertNull($missing[$items[2]->id]);

        $distribution=PeriodWeightDistribution::create(['department_id'=>$department->id,'year'=>2026,'period'=>'july','mode'=>'manual','status'=>'approved','assigned_by'=>$user->id,'assigned_at'=>now(),'approved_by'=>$user->id,'approved_at'=>now()]);
        $distribution->allocations()->createMany([
            ['plan_item_id'=>$items[0]->id,'original_weight'=>.5,'effective_weight'=>.7],
            ['plan_item_id'=>$items[1]->id,'original_weight'=>.3,'effective_weight'=>null],
            ['plan_item_id'=>$items[2]->id,'original_weight'=>.1,'effective_weight'=>.3],
            ['plan_item_id'=>$items[3]->id,'original_weight'=>.1,'effective_weight'=>null],
        ]);
        $resolved=$resolver->forPeriod($plan->items,'july');
        $this->assertEqualsWithDelta(.7,$resolved[$items[0]->id],.0001);
        $this->assertEqualsWithDelta(.3,$resolved[$items[2]->id],.0001);
        $this->assertNull($resolved[$items[1]->id]);
        $this->assertSame('0.10',$items[2]->fresh()->weight);
    }
}
