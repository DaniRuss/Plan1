<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\HistoricalKpi;
use App\Models\HistoricalPerformanceImport;
use App\Models\StrategicObjective;
use App\Models\User;
use App\Services\StableKpiCrosswalk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalKpiCrosswalkTest extends TestCase
{
    use RefreshDatabase;

    public function test_crosswalk_defines_every_permanent_kpi_once_on_each_side(): void
    {
        $definitions = StableKpiCrosswalk::definitions();

        $this->assertCount(27, $definitions);
        $this->assertSame(array_map(fn ($n) => 'K'.$n, range(1, 27)), array_column($definitions, 'kpi_code'));
        $this->assertCount(27, array_unique(array_column($definitions, 'historical_code')));
        $this->assertCount(27, array_unique(array_column($definitions, 'current_code')));
    }

    public function test_apply_updates_only_kpi_codes_and_trusts_exact_permanent_code_mappings(): void
    {
        $user = User::factory()->create(['is_active'=>true]);
        $department = Department::create(['name'=>'Planning','code'=>'PLN','is_active'=>true,'reporting_type'=>'monthly']);
        $import = HistoricalPerformanceImport::create(['year'=>2018,'original_filename'=>'2018.xlsx','source_hash'=>str_repeat('c',64),'status'=>'locked','imported_by'=>$user->id]);
        $snapshots=[];
        foreach (StableKpiCrosswalk::definitions() as $definition) {
            $historical=HistoricalKpi::create(['historical_performance_import_id'=>$import->id,'source_row'=>100+$definition['number'],'code'=>$definition['historical_code'],'description'=>$definition['historical_description'],'unit'=>'%','annual_target'=>80,'goal_direction'=>'increase','direction_status'=>'confirmed','unit_status'=>'specified','is_main_objective'=>$definition['historical_main']]);
            $current=StrategicObjective::create(['code'=>$definition['current_code'],'description'=>$definition['current_description'],'department_id'=>$department->id,'unit'=>'%','annual_target'=>90,'weight'=>1,'goal_direction'=>'increase','status'=>'approved','is_active'=>true]);
            $snapshots[$historical->id]=[$historical->description,$historical->annual_target,$current->id,$current->description,$current->annual_target];
        }

        $result=app(StableKpiCrosswalk::class)->apply($import,$user);

        $this->assertSame(27,$result['assigned']);
        $this->assertSame(array_map(fn($n)=>'K'.$n,range(1,27)),$import->kpis()->orderByRaw('CAST(SUBSTRING(kpi_code, 2) AS UNSIGNED)')->pluck('kpi_code')->all());
        $this->assertSame(27,$import->kpis()->whereHas('mappings',fn($q)=>$q->where('mapping_type','direct')->where('status','approved')->where('comparable',true)->whereNotNull('reviewed_at'))->count());
        foreach($snapshots as $historicalId=>$before){$historical=HistoricalKpi::findOrFail($historicalId);$current=StrategicObjective::findOrFail($before[2]);$this->assertSame($before[0],$historical->description);$this->assertEquals($before[1],$historical->annual_target);$this->assertSame($before[3],$current->description);$this->assertEquals($before[4],$current->annual_target);}
    }
}
