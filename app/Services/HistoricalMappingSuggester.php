<?php

namespace App\Services;

use App\Models\{HistoricalKpiMapping,HistoricalPerformanceImport,StrategicObjective,User};

class HistoricalMappingSuggester
{
    public function suggest(HistoricalPerformanceImport $import, User $user): int
    {
        $objectives=StrategicObjective::where('is_active',true)->whereNotNull('kpi_code')->orWhere(fn($q)=>$q->where('is_active',true)->whereNotNull('code'))->get();
        $created=0;
        foreach($import->kpis()->where('is_main_objective',false)->whereDoesntHave('mappings')->get() as $historical){
            $best=null;$confidence=0;$type='renamed';
            foreach($objectives as $objective){
                $score=0;
                if($historical->kpi_code && $historical->kpi_code===$objective->kpi_code){$score=100;$candidateType='direct';}
                elseif($historical->code && in_array($historical->code,array_filter([$objective->kpi_code,$objective->code]),true)){$score=88;$candidateType='direct';}
                else{similar_text($this->normalize($historical->description),$this->normalize($objective->description),$score);$candidateType=$score>=99?'direct':'renamed';}
                if($score>$confidence){$best=$objective;$confidence=$score;$type=$candidateType;}
            }
            if(!$best || $confidence<70) continue;
            $samePermanentKpi=$historical->kpi_code && $historical->kpi_code===$best->kpi_code;
            $sameUnit=$historical->normalized_unit && $historical->normalized_unit===app(HistoricalPerformanceWorkbook::class)->normalizeUnit($best->unit);
            HistoricalKpiMapping::create(['historical_kpi_id'=>$historical->id,'current_objective_id'=>$best->id,'mapping_type'=>$type,'comparable'=>$samePermanentKpi||$sameUnit,'confidence'=>round($confidence,2),'expert_comment'=>$samePermanentKpi?'Permanent KPI code match; reviewer approval required.':'System suggestion; expert review required.','status'=>'draft','prepared_by'=>$user->id]);
            $created++;
        }
        return $created;
    }
    private function normalize(string $value): string{return preg_replace('/[^a-z0-9]+/',' ',mb_strtolower($value));}
}
