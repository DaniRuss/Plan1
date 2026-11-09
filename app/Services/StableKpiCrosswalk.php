<?php

namespace App\Services;

use App\Models\{AuditLog,HistoricalKpi,HistoricalKpiMapping,HistoricalPerformanceImport,StrategicObjective,User};
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StableKpiCrosswalk
{
    public static function definitions(): array
    {
        $rows = [
            ['2.1','Improve train punctuality and reliability rates from 50.7% to 72% (by 42%).',false,'2.1','Improve train punctuality and reliability rates'],
            ['4.12','Achieving 100% on-time delivery rate',false,'4.11','Achieve on-time delivery rate'],
            ['12.1','Conduct customer satisfaction survey biannually and achieve 80% satisfaction.',false,'13.3','Conduct customer satisfaction survey'],
            ['2','Objective 2: Improve freight train operational efficiency',true,'2','Improve freight train operational efficiency'],
            ['3.4','Improve seat occupancy rate from 66% to 90%',false,'3.4','Improve seat occupancy rate'],
            ['1','Objective 1: Increase cargo carrying capacity to 3.8 million tons',true,'1','Transport 4.3 million tons of cargo'],
            ['4.14','Reduce cargo damage rate to less than 2%',false,'4.13','Reduce cargo damage rate'],
            ['1.12','Improve national import cargo transportation share',false,'1.15','Improve national import cargo transportation share'],
            ['3','Objective 3: Increase passenger volume to 326,016',true,'3','Objective 3: Transport 586,327 passengers'],
            ['5.4','Improve maintenance time for emergency equipment failure to 85% of the standard',false,'6.4','Improve maintenance time for emergency equipment failure to the standard'],
            ['6.6','Improve locomotive fleet Utilization Rate 85%',false,'7.6','Improve locomotive fleet Utilization Rate'],
            ['10.6','Achieve 70 on-time project completion rate',false,'11.7','Achieve on-time project completion rate'],
            ['6.7','Improve rolling stock functionality from 86 to 92%',false,'7.7','Improve rolling stock functionality'],
            ['5','Objective 5: Improve infrastructure availability from 92% to 94%',true,'6','Objective 6: Improve infrastructure availability from 92% to 96%'],
            ['5.1','Keep average Track Quality Index of the line below and equal 7.5',false,'6.1','Improve average Track Quality Index of the line'],
            ['5.2','Keep OCS quality evaluation excellent rate from 98% to 99%',false,'6.2','Keep OCS quality evaluation excellent rate'],
            ['15','Objective 15: Increase revenue to birr 13.2 billion',true,'16','Objective 16: Generate Total Revenue'],
            ['16.1','Achieve a net profit margin from 18%',false,'17.1','Achieve Corporate profit margin'],
            ['16.2','Prepare and implement cost optimization strategy',false,'17.2','Prepare and implement cost optimization strategy'],
            ['10.7','Keep cost variance rate within 40% of the planned budget.',false,'11.8','Keep cost variance rate within of the planned budget.'],
            ['13.3','Complete 70% purchase requests on the specified time schedule',false,'14.2','Complete purchase requests on the specified time schedule'],
            ['14','Objective 14: Digitalize 100% of the company services and operational activities',true,'15','Objective 15: Digitalize 100% of the company services and operational activities'],
            ['4.18','Implement 80% digital Transport Management System (TMS) for booking, real-time tracking, and invoicing and integrate with stakeholders.',false,'15.2','Develop and implement Integrated Transport and Logistics Management System (ITLMS)'],
            ['7','Objective 7: Implement safety management system and reduce railway traffic accident/incident rate by 50%',true,'8','Objective 8: Improve safety management system and ensure ZERO accident/incidents'],
            ['8','Objective 8: Improve community engagement and reduce security incidents by 60%',true,'9','Objective 9: Improve community engagement and ensure ZERO security incidents'],
            ['11','Objective 11: Achieve 95% employee retention',true,'12','Objective 12: Achieve 97.5% employee retention'],
            ['11.12','Perform employee’s performance appraisal and recognize top performers quarterly.',false,'12.8','Perform employee’s performance appraisal and recognize top performers biannually.'],
        ];
        return array_map(fn ($row,$index) => ['number'=>$index+1,'kpi_code'=>'K'.($index+1),'historical_code'=>$row[0],'historical_description'=>$row[1],'historical_main'=>$row[2],'current_code'=>$row[3],'current_description'=>$row[4]],$rows,array_keys($rows));
    }

    public function apply(HistoricalPerformanceImport $import, User $user): array
    {
        $definitions=static::definitions();
        $expected=array_map(fn($n)=>'K'.$n,range(1,27));
        if(count($definitions)!==27 || array_column($definitions,'kpi_code')!==$expected) throw new RuntimeException('Stable KPI crosswalk must contain K1 through K27 exactly once.');

        $resolved=[];
        foreach($definitions as $definition){
            $historical=$import->kpis()->where('code',$definition['historical_code'])->where('description',$definition['historical_description'])->get();
            $current=StrategicObjective::where('code',$definition['current_code'])->where('description',$definition['current_description'])->get();
            if($historical->count()!==1 || $current->count()!==1) throw new RuntimeException("{$definition['kpi_code']} endpoint resolution failed: historical={$historical->count()}, current={$current->count()}.");
            $resolved[]=$definition+['historical'=>$historical->first(),'current'=>$current->first()];
        }

        $result=DB::transaction(function() use($import,$user,$resolved,$expected){
            $historicalIds=collect($resolved)->pluck('historical.id');$currentIds=collect($resolved)->pluck('current.id');
            $import->kpis()->whereIn('kpi_code',$expected)->whereNotIn('id',$historicalIds)->update(['kpi_code'=>null]);
            StrategicObjective::whereIn('kpi_code',$expected)->whereNotIn('id',$currentIds)->update(['kpi_code'=>null]);
            $mappingCount=0;
            foreach($resolved as $entry){
                $historical=$entry['historical'];$current=$entry['current'];
                $historical->update(['kpi_code'=>$entry['kpi_code'],'updated_by'=>$user->id]);
                $current->update(['kpi_code'=>$entry['kpi_code']]);
                $mapping=HistoricalKpiMapping::where('historical_kpi_id',$historical->id)->where('current_objective_id',$current->id)->first();
                if(!$mapping)$mapping=HistoricalKpiMapping::where('historical_kpi_id',$historical->id)->whereIn('status',['draft','rejected'])->oldest('id')->first() ?? new HistoricalKpiMapping(['historical_kpi_id'=>$historical->id]);
                $alreadyApproved=$mapping->exists && $mapping->status==='approved';
                $mapping->fill(['current_objective_id'=>$current->id,'mapping_type'=>'direct','comparable'=>true,'confidence'=>100,'expert_comment'=>'Permanent KPI crosswalk '.$entry['kpi_code'].': same institutional KPI; wording may differ by plan year.','prepared_by'=>$user->id,'status'=>'approved','version'=>($mapping->version?:0)+1]);
                if(!$alreadyApproved){$mapping->reviewed_by=$user->id;$mapping->reviewed_at=now();$mapping->review_comment='Automatically trusted because both years use the same permanent institutional KPI code.';}
                $mapping->save();$mappingCount++;
            }
            return ['assigned'=>count($resolved),'mappings'=>$mappingCount];
        });
        AuditLog::log('stable_kpi_crosswalk_applied',$import,$result+['codes'=>$expected]);
        return $result;
    }
}
