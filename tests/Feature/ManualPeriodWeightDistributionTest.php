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

    public function test_nested_planned_children_leave_only_point_two_to_distribute(): void
    {
        $department=Department::create(['name'=>'Operation','code'=>'OP','is_active'=>true]);
        $user=User::factory()->create(['department_id'=>$department->id,'is_active'=>true,'must_change_password'=>false]);
        $user->roles()->attach(\App\Models\Role::create(['name'=>'system_admin']));
        $root=StrategicObjective::create(['code'=>'1','description'=>'Transport','weight'=>5.7,'unit'=>'Tons','department_id'=>$department->id]);
        $plan=Plan::create(['title'=>'Nested plan','type'=>'annual','year'=>2026,'department_id'=>$department->id,'status'=>'approved','created_by'=>$user->id]);
        $make=function($code,$weight,$target,$parent) use($department,$plan) {
            $objective=StrategicObjective::create(['code'=>$code,'description'=>$code,'weight'=>$weight,'unit'=>'Tons','department_id'=>$department->id,'parent_id'=>$parent,'status'=>'approved']);
            return PlanItem::create(['plan_id'=>$plan->id,'strategic_objective_id'=>$objective->id,'weight'=>$weight,'july_target'=>$target]);
        };
        $branch=$make('1.1',3,null,$root->id);
        $a=$make('1.1.1',1.5,0,$branch->strategic_objective_id);
        $b=$make('1.1.2',1.5,10,$branch->strategic_objective_id);
        $other=$make('1.2',2.5,20,$root->id);
        $unplanned=$make('1.14',.2,null,$root->id);
        $query=['department_id'=>$department->id,'year'=>2026,'period'=>'july'];
        $response=$this->actingAs($user)->get(route('period-weights.edit',$query))->assertOk();
        $response->assertSee('Extra weight to distribute')->assertSee('data-total="0.2"',false);
        $response->assertSee('Planned through sub-objectives');
        $this->post(route('period-weights.store'),$query+['action'=>'submit','additions'=>[$branch->id=>.1,$other->id=>.1]])->assertRedirect()->assertSessionHasNoErrors();
        $distribution=PeriodWeightDistribution::firstOrFail();
        $this->assertEquals(3.1,$distribution->allocations()->where('plan_item_id',$branch->id)->firstOrFail()->effective_weight);
        $this->assertEquals(2.6,$distribution->allocations()->where('plan_item_id',$other->id)->firstOrFail()->effective_weight);
        $this->assertNull($distribution->allocations()->where('plan_item_id',$unplanned->id)->firstOrFail()->effective_weight);
        $distribution->update(['status'=>'approved']);
        $resolved=app(PeriodWeightNormalizer::class)->manualForPeriod($plan->items()->with('strategicObjective')->get(),'july');
        $this->assertEquals(3.1,$resolved[$branch->id]);
        $this->assertEquals(1.5,$resolved[$a->id]);
        $this->assertEquals(1.5,$resolved[$b->id]);
        // Stored approved allocations must not be recomputed after target changes.
        $other->update(['july_target'=>null]);
        $resolved=app(PeriodWeightNormalizer::class)->manualForPeriod($plan->fresh()->items()->with('strategicObjective')->get(),'july');
        $this->assertEquals(2.6,$resolved[$other->id]);
        $this->get(route('period-weights.edit',$query))->assertOk()->assertSee('data-total="0.2"',false);
        $this->assertEquals(3,$branch->fresh()->weight);
    }

    public function test_only_extra_weight_is_accepted_and_locked_allocations_cannot_be_overwritten(): void
    {
        $department=Department::create(['name'=>'Extra weights','code'=>'EX','is_active'=>true]);
        $user=User::factory()->create(['department_id'=>$department->id,'is_active'=>true,'must_change_password'=>false]);
        $user->roles()->attach(\App\Models\Role::create(['name'=>'system_admin']));
        $plan=Plan::create(['title'=>'Extra plan','type'=>'annual','year'=>2026,'department_id'=>$department->id,'status'=>'approved','created_by'=>$user->id]);
        $items=collect();
        foreach ([[.5,10],[.3,null],[.1,0],[.1,null]] as $i=>[$weight,$target]) {
            $objective=StrategicObjective::create(['code'=>'8.'.($i+1),'description'=>'Activity','weight'=>$weight,'unit'=>'Number','department_id'=>$department->id]);
            $items->push(PlanItem::create(['plan_id'=>$plan->id,'strategic_objective_id'=>$objective->id,'weight'=>$weight,'july_target'=>$target]));
        }
        $query=['department_id'=>$department->id,'year'=>2026,'period'=>'july','action'=>'submit'];
        foreach ([[-.1,.5],[.5,0],[.7,.3]] as [$a,$b]) {
            $this->actingAs($user)->post(route('period-weights.store'),$query+['additions'=>[$items[0]->id=>$a,$items[2]->id=>$b]])->assertSessionHasErrors();
        }
        $this->post(route('period-weights.store'),$query+['additions'=>[$items[1]->id=>.4]])->assertSessionHasErrors();
        $this->post(route('period-weights.store'),$query+['additions'=>[$items[0]->id=>.2,$items[2]->id=>.2]])->assertSessionHasNoErrors();
        $distribution=PeriodWeightDistribution::firstOrFail();
        $this->assertEquals(.7,$distribution->allocations()->where('plan_item_id',$items[0]->id)->firstOrFail()->effective_weight);
        $distribution->update(['status'=>'approved']);
        $this->post(route('period-weights.store'),$query+['additions'=>[$items[0]->id=>.2,$items[2]->id=>.2]])->assertStatus(422);
    }

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
